<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Ce test doit être exécuté avec PHP CLI.\n");
    exit(1);
}

if (!class_exists(DOMDocument::class)) {
    fwrite(STDERR, "Test HTML/accessibilité : ÉCHEC - l’extension DOM est requise.\n");
    exit(1);
}

$root = dirname(__DIR__);

try {
    $templateFiles = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root . '/var', FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
        if (str_starts_with($relative, 'var/private/')) {
            continue;
        }
        if ($file->isFile() && strtolower($file->getExtension()) === 'tpl') {
            $templateFiles[] = $file->getPathname();
        }
    }
    sort($templateFiles);
    accessibilityAssert($templateFiles !== [], 'aucun template trouvé');

    foreach ($templateFiles as $file) {
        $relative = str_replace('\\', '/', substr($file, strlen($root) + 1));
        $source = (string) file_get_contents($file);
        accessibilityAssert(
            preg_match('/<IF\s|<ENDIF>|<!--\s*(?:BEGIN|END|CHILD|FILE)\b/', $source) !== 1,
            $relative . ' : syntaxe historique de template'
        );
        foreach ([
            '/<(?:font|center|marquee|frame|frameset)\b/i' => 'élément HTML obsolète',
            '/<(?:b|i)\b/i' => 'balise de présentation b/i',
            '/\sstyle\s*=/i' => 'style intégré',
            '/\son[a-z]+\s*=/i' => 'gestionnaire JavaScript intégré',
            '/javascript\s*:/i' => 'URL JavaScript',
            '/\saccesskey\s*=/i' => 'raccourci clavier imposé',
            '/\stabindex\s*=\s*["\']\s*[1-9][0-9]*\s*["\']/i' => 'tabindex positif',
            '/<meta\b[^>]*\bhttp-equiv\s*=\s*["\']content-type["\']/i' => 'déclaration d’encodage HTML historique',
            '/<script\b[^>]*\btype\s*=\s*["\']text\/javascript["\']/i' => 'type JavaScript inutile en HTML5',
            '/<link\b[^>]*\btype\s*=\s*["\']text\/css["\']/i' => 'type CSS inutile en HTML5',
        ] as $pattern => $description) {
            accessibilityAssert(
                preg_match($pattern, $source) !== 1,
                $relative . ' : ' . $description
            );
        }

        preg_match_all('/<img\b[^>]*>/i', $source, $images);
        foreach ($images[0] as $image) {
            accessibilityAssert(
                preg_match('/\salt\s*=\s*(["\']).*?\1/is', $image) === 1,
                $relative . ' : image sans attribut alt'
            );
        }

        $html = preg_replace('/\{%.*?%\}/s', '', $source) ?? $source;
        $html = preg_replace('/\{\{\s*[A-Za-z_][A-Za-z0-9_.]*\s*\}\}/', 'placeholder', $html) ?? $html;
        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument();
        $loaded = $document->loadHTML(
            '<!doctype html><html><body>' . $html . '</body></html>',
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        accessibilityAssert($loaded, $relative . ' : fragment HTML illisible');
        $xpath = new DOMXPath($document);

        $ids = [];
        foreach ($xpath->query('//*[@id]') as $element) {
            if (!$element instanceof DOMElement) {
                continue;
            }
            $id = trim($element->getAttribute('id'));
            accessibilityAssert($id !== '', $relative . ' : identifiant HTML vide');
            accessibilityAssert(!isset($ids[$id]), $relative . ' : identifiant dupliqué ' . $id);
            $ids[$id] = true;
        }

        foreach ($xpath->query('//input|//select|//textarea') as $control) {
            if (!$control instanceof DOMElement) {
                continue;
            }
            $type = strtolower($control->getAttribute('type'));
            if ($control->tagName === 'input'
                && in_array($type, ['hidden', 'submit', 'button', 'reset'], true)) {
                continue;
            }
            accessibilityAssert(
                accessibilityHasName($control, $xpath, $ids),
                $relative . ' : champ sans nom accessible ('
                    . $control->tagName . ' ' . $control->getAttribute('name') . ')'
            );
        }

        foreach ($xpath->query('//button|//a[@href]') as $interactive) {
            if (!$interactive instanceof DOMElement) {
                continue;
            }
            accessibilityAssert(
                accessibilityHasName($interactive, $xpath, $ids),
                $relative . ' : lien ou bouton sans nom accessible'
            );
        }

        foreach ($document->getElementsByTagName('table') as $table) {
            accessibilityAssert(
                $table->getElementsByTagName('caption')->length === 1,
                $relative . ' : tableau de données sans légende'
            );
            foreach ($table->getElementsByTagName('th') as $heading) {
                accessibilityAssert(
                    in_array(strtolower($heading->getAttribute('scope')), ['col', 'row'], true),
                    $relative . ' : en-tête de tableau sans attribut scope'
                );
            }
        }

        preg_match_all('/<[^>]*class=["\'][^"\']*\bstatus-message\b[^"\']*["\'][^>]*>/i', $source, $statusTags);
        foreach ($statusTags[0] as $statusTag) {
            accessibilityAssert(
                preg_match('/\srole\s*=\s*(["\'])status\1/i', $statusTag) === 1,
                $relative . ' : message d’état sans role="status"'
            );
        }

        foreach ($document->getElementsByTagName('dialog') as $dialog) {
            if (!$dialog instanceof DOMElement) {
                continue;
            }
            $hasLabel = trim($dialog->getAttribute('aria-label')) !== '';
            $labelledBy = trim($dialog->getAttribute('aria-labelledby'));
            if ($labelledBy !== '') {
                $hasLabel = isset($ids[$labelledBy]);
            }
            accessibilityAssert($hasLabel, $relative . ' : boîte de dialogue sans nom accessible');
        }

        if (str_contains($source, 'class="page-heading"')
            || str_contains($source, 'class="content-heading"')) {
            accessibilityAssert(
                preg_match('/<h1\b/i', $source) === 1,
                $relative . ' : en-tête de page sans titre h1'
            );
        }
    }

    $themeDirectories = glob($root . '/var/templates/*', GLOB_ONLYDIR) ?: [];
    $publicThemes = [];
    foreach ($themeDirectories as $themeDirectory) {
        $themeName = basename($themeDirectory);
        $shellFile = $themeDirectory . '/index.tpl';
        $cssFile = $themeDirectory . '/style.css';
        if (!is_file($shellFile) || !is_file($cssFile)) {
            continue;
        }
        $publicThemes[$themeName] = [
            'shell' => (string) file_get_contents($shellFile),
            'css' => (string) file_get_contents($cssFile),
        ];
    }
    accessibilityAssert(count($publicThemes) >= 1, 'aucun thème public complet trouvé');

    $adminShell = (string) file_get_contents($root . '/var/admin/index.tpl');
    foreach ($publicThemes as $themeName => $themeData) {
        $shell = $themeData['shell'];
        $css = $themeData['css'];
        accessibilityAssert(
            preg_match('/<main\b/i', $shell) === 1
                && preg_match('/<header\b/i', $shell) === 1
                && preg_match('/<nav\b/i', $shell) === 1
                && preg_match('/<footer\b/i', $shell) === 1,
            'thème ' . $themeName . ' : landmarks principaux incomplets'
        );
        accessibilityAssert(
            preg_match('/class="skip-link"[^>]+href="#main-content"/i', $shell) === 1,
            'thème ' . $themeName . ' : lien d’évitement vers le contenu principal absent'
        );
        accessibilityAssert(
            str_contains($shell, 'href="login.html"')
                && !str_contains($shell, '{% slot users %}')
                && str_contains($shell, 'Admin.html#content-management')
                && str_contains($shell, 'Admin.html#administration'),
            'thème ' . $themeName . ' : connexion discrète ou accès de rôle incomplets'
        );
        accessibilityAssert(
            str_contains($shell, 'var/templates/' . $themeName . '/style.min.css'),
            'thème ' . $themeName . ' : feuille publique minifiée non chargée'
        );
        accessibilityAssert(
            substr_count(strtolower($shell), '<meta charset=') === 1,
            'thème ' . $themeName . ' : une déclaration meta charset unique est requise'
        );
        accessibilityAssert(
            str_contains($css, '@media (prefers-reduced-motion: reduce)'),
            'thème ' . $themeName . ' : réduction des animations absente'
        );
        accessibilityAssert(
            str_contains($css, ':focus-visible')
                && preg_match('/outline:\s*3px\s+solid/i', $css) === 1,
            'thème ' . $themeName . ' : focus visible insuffisant'
        );
        accessibilityAssert(
            !str_contains($css, 'min-width: 320px')
                && str_contains($css, '@media (max-width: 380px)'),
            'thème ' . $themeName . ' : reflow sous 320 CSS px non garanti'
        );
        accessibilityAssert(
            str_contains($css, 'min-width: 44px;')
                && str_contains($css, 'height: 44px;'),
            'thème ' . $themeName . ' : cibles interactives principales trop petites'
        );
        accessibilityAssert(
            str_contains($css, 'border: 1px solid #7a899c;'),
            'thème ' . $themeName . ' : limites visuelles des champs insuffisantes'
        );
    }

    accessibilityAssert(
        preg_match('/<main\b/i', $adminShell) === 1
            && preg_match('/<header\b/i', $adminShell) === 1
            && preg_match('/<nav\b/i', $adminShell) === 1
            && preg_match('/<footer\b/i', $adminShell) === 1,
        'administration : landmarks principaux incomplets'
    );
    accessibilityAssert(
        preg_match('/class="skip-link"[^>]+href="#admin-content"/i', $adminShell) === 1,
        'administration : lien d’évitement vers le contenu principal absent'
    );
    accessibilityAssert(
        substr_count(strtolower($adminShell), '<meta charset=') === 1,
        'administration : une déclaration meta charset unique est requise'
    );

    $adminCss = (string) file_get_contents($root . '/var/admin/style.css');
    accessibilityAssert(
        str_contains($adminCss, '@media (prefers-reduced-motion: reduce)'),
        'la réduction des animations doit être respectée dans l’administration'
    );
    accessibilityAssert(
        str_contains($adminCss, 'outline: 3px solid var(--accent);')
            && str_contains($adminCss, '.admin-topbar a:focus-visible')
            && str_contains($adminCss, 'outline-color: #fff;'),
        'l’administration doit fournir un focus opaque et contrasté sur fonds clairs et sombres'
    );
    accessibilityAssert(
        str_contains($adminCss, '@media (max-width: 480px)')
            && str_contains($adminCss, 'min-height: 44px;')
            && str_contains($adminCss, 'min-width: 44px;'),
        'l’administration doit rester utilisable sur petit écran avec des cibles confortables'
    );
    accessibilityAssert(
        str_contains($adminCss, 'border: 1px solid #7a899c;'),
        'les limites visuelles des champs administratifs doivent rester suffisamment contrastées'
    );
    accessibilityAssert(
        str_contains($adminShell, 'class="admin-layout"')
            && str_contains($adminShell, 'id="admin-sidebar"')
            && str_contains($adminShell, 'aria-controls="admin-sidebar"')
            && str_contains($adminShell, 'aria-expanded="false"'),
        'la navigation administrative doit être nommée et contrôlable sur petit écran'
    );
    accessibilityAssert(
        str_contains($adminCss, '.action-content')
            && str_contains($adminCss, '.action-admin')
            && str_contains($adminCss, '.button-enable')
            && str_contains($adminCss, '.button-disable'),
        'l’administration doit conserver le code couleur contenu/configuration et activation/désactivation'
    );

    $editorCss = (string) file_get_contents($root . '/var/editor/portix-editor.css');
    accessibilityAssert(
        str_contains($editorCss, 'border-color: #7a899c;')
            && str_contains($editorCss, '@media (max-width: 480px)'),
        'le WYSIWYG doit conserver des limites visibles et se redistribuer sur petit écran'
    );

    $editorScript = (string) file_get_contents($root . '/var/editor/portix-editor.js');
    accessibilityAssert(
        str_contains($editorScript, "setAttribute('role', 'toolbar')")
            && str_contains($editorScript, "setAttribute('aria-label', editorLabel)")
            && str_contains($editorScript, "setAttribute('aria-multiline', 'true')"),
        'le WYSIWYG doit exposer une barre d’outils et une zone de saisie nommées'
    );

    $uiScript = (string) file_get_contents($root . '/var/editor/portix-ui.js');
    accessibilityAssert(
        str_contains($uiScript, "['.nav-toggle', '#main-navigation']")
            && str_contains($uiScript, "event.key !== 'Escape'"),
        'la navigation publique responsive doit pouvoir être refermée au clavier avec Échap'
    );

    foreach ([
        [
            'source' => '/var/modules/articles/src/ArticleOrganizer.php',
            'label' => 'var/modules/articles/templates/admin-order.tpl',
        ],
        [
            'source' => '/var/modules/menu/templates/admin.tpl',
            'label' => 'var/modules/menu/templates/admin.tpl',
        ],
    ] as $sortableInterface) {
        $source = (string) file_get_contents($root . $sortableInterface['source']);
        accessibilityAssert(
            str_contains($source, 'data-move="up"') && str_contains($source, 'data-move="down"'),
            $sortableInterface['label'] . ' : alternative clavier au glisser-déposer absente'
        );
    }

    echo count($templateFiles) . " template(s) contrôlé(s).\n";
    echo "HTML source et accessibilité automatisable : OK\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Test HTML/accessibilité : ÉCHEC - ' . $error->getMessage() . "\n");
    exit(1);
}

/** @param array<string, bool> $ids */
function accessibilityHasName(DOMElement $element, DOMXPath $xpath, array $ids): bool
{
    if (trim($element->getAttribute('aria-label')) !== '') {
        return true;
    }
    $labelledBy = preg_split('/\s+/', trim($element->getAttribute('aria-labelledby'))) ?: [];
    foreach ($labelledBy as $id) {
        if ($id !== '' && isset($ids[$id])) {
            return true;
        }
    }
    if (in_array(strtolower($element->tagName), ['button', 'a'], true)
        && trim($element->textContent) !== '') {
        return true;
    }
    $id = trim($element->getAttribute('id'));
    if ($id !== '') {
        foreach ($xpath->query('//label[@for]') as $label) {
            if ($label instanceof DOMElement
                && $label->getAttribute('for') === $id
                && trim($label->textContent) !== '') {
                return true;
            }
        }
    }
    for ($parent = $element->parentNode; $parent !== null; $parent = $parent->parentNode) {
        if ($parent instanceof DOMElement && strtolower($parent->tagName) === 'label') {
            return trim($parent->textContent) !== '';
        }
    }
    return false;
}

function accessibilityAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
