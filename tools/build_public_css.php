<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Cet outil doit être exécuté en ligne de commande.\n");
}

/**
 * Construit la feuille publique d'un thème à partir de sa source et des styles
 * communs du contenu riche. L'ordre reproduit les deux anciennes balises link.
 */
function portixCompilePublicCss(string $themeCss, string $contentCss): string
{
    return portixMinifyCss($themeCss . "\n" . $contentCss) . "\n";
}

/**
 * Minification conservative : commentaires et espaces inutiles sont retirés,
 * sans modifier les chaînes ni les espaces requis dans calc().
 */
function portixMinifyCss(string $css): string
{
    $length = strlen($css);
    $collapsed = '';
    $quote = null;
    $escaped = false;
    $pendingSpace = false;

    for ($index = 0; $index < $length; $index++) {
        $character = $css[$index];

        if ($quote !== null) {
            $collapsed .= $character;
            if ($escaped) {
                $escaped = false;
            } elseif ($character === '\\') {
                $escaped = true;
            } elseif ($character === $quote) {
                $quote = null;
            }
            continue;
        }

        if ($character === '/' && $index + 1 < $length && $css[$index + 1] === '*') {
            $commentEnd = strpos($css, '*/', $index + 2);
            if ($commentEnd === false) {
                throw new RuntimeException('Commentaire CSS non terminé.');
            }
            $index = $commentEnd + 1;
            continue;
        }

        if ($character === '"' || $character === "'") {
            if ($pendingSpace && $collapsed !== '') {
                $collapsed .= ' ';
            }
            $pendingSpace = false;
            $quote = $character;
            $collapsed .= $character;
            continue;
        }

        if (str_contains(" \n\r\t\f", $character)) {
            $pendingSpace = true;
            continue;
        }

        if ($pendingSpace && $collapsed !== '') {
            $collapsed .= ' ';
        }
        $pendingSpace = false;
        $collapsed .= $character;
    }

    if ($quote !== null) {
        throw new RuntimeException('Chaîne CSS non terminée.');
    }

    $result = '';
    $length = strlen($collapsed);
    $quote = null;
    $escaped = false;
    for ($index = 0; $index < $length; $index++) {
        $character = $collapsed[$index];

        if ($quote !== null) {
            $result .= $character;
            if ($escaped) {
                $escaped = false;
            } elseif ($character === '\\') {
                $escaped = true;
            } elseif ($character === $quote) {
                $quote = null;
            }
            continue;
        }

        if ($character === '"' || $character === "'") {
            $quote = $character;
            $result .= $character;
            continue;
        }

        if ($character === ' ') {
            $previous = $result === '' ? '' : $result[strlen($result) - 1];
            $next = $index + 1 < $length ? $collapsed[$index + 1] : '';
            if (str_contains('{};,', $previous)
                || str_contains('{};,', $next)
                || $previous === ':') {
                continue;
            }
        }

        if ($character === '}' && $result !== '' && $result[strlen($result) - 1] === ';') {
            $result = substr($result, 0, -1);
        }
        $result .= $character;
    }

    return trim($result);
}

/** @return list<string> */
function portixPublicThemeNames(string $root): array
{
    $themes = [];
    foreach (glob($root . '/var/templates/*', GLOB_ONLYDIR) ?: [] as $directory) {
        if (is_file($directory . '/index.tpl') && is_file($directory . '/style.css')) {
            $themes[] = basename($directory);
        }
    }
    sort($themes);

    return $themes;
}

function portixExpectedPublicCss(string $root, string $theme): string
{
    $themeFile = $root . '/var/templates/' . $theme . '/style.css';
    $contentFile = $root . '/var/editor/portix-content.css';
    if (!is_file($themeFile) || !is_file($contentFile)) {
        throw new RuntimeException('Source CSS introuvable pour le thème ' . $theme . '.');
    }

    return portixCompilePublicCss(
        (string) file_get_contents($themeFile),
        (string) file_get_contents($contentFile)
    );
}

function portixBuildPublicCssMain(array $arguments): int
{
    $root = dirname(__DIR__);
    $checkOnly = false;
    foreach (array_slice($arguments, 1) as $argument) {
        if ($argument === '--check') {
            $checkOnly = true;
            continue;
        }
        fwrite(STDERR, 'Option inconnue : ' . $argument . PHP_EOL);
        return 2;
    }

    $themes = portixPublicThemeNames($root);
    if ($themes === []) {
        fwrite(STDERR, "Aucun thème public complet à compiler.\n");
        return 1;
    }

    $stale = [];
    foreach ($themes as $theme) {
        $target = $root . '/var/templates/' . $theme . '/style.min.css';
        $expected = portixExpectedPublicCss($root, $theme);
        if ($checkOnly) {
            if (!is_file($target) || file_get_contents($target) !== $expected) {
                $stale[] = 'var/templates/' . $theme . '/style.min.css';
            }
            continue;
        }
        if (file_put_contents($target, $expected, LOCK_EX) === false) {
            fwrite(STDERR, 'Écriture impossible : ' . $target . PHP_EOL);
            return 1;
        }
    }

    if ($stale !== []) {
        fwrite(STDERR, "Feuilles publiques absentes ou obsolètes :\n- " . implode("\n- ", $stale) . "\n");
        fwrite(STDERR, "Lancez : php tools/build_public_css.php\n");
        return 1;
    }

    echo count($themes) . ($checkOnly
        ? " feuille(s) publique(s) à jour.\n"
        : " feuille(s) publique(s) compilée(s) et minifiée(s).\n");
    return 0;
}

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    exit(portixBuildPublicCssMain($argv));
}
