<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Ce test doit être exécuté avec PHP CLI.\n");
    exit(1);
}

require_once dirname(__DIR__) . '/bootstrap/autoload.php';

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'portix3-installer-' . bin2hex(random_bytes(5));
$status = 0;

try {
    if (!mkdir($root, 0770, true) && !is_dir($root)) {
        throw new RuntimeException('Impossible de créer le dossier temporaire.');
    }

    $preflight = (new Portix\Installation\EnvironmentPreflight($root))->run();
    foreach ($preflight['directories'] as $check) {
        if (!$check['ok']) {
            throw new RuntimeException(
                'Le contrôle du dossier ' . $check['label'] . ' a échoué : ' . $check['detail']
            );
        }
    }

    foreach (Portix\Installation\EnvironmentPreflight::writableDirectories() as $relative) {
        $directory = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        if (!is_dir($directory)) {
            throw new RuntimeException('Le dossier ' . $relative . ' n’a pas été créé.');
        }
        $leftovers = glob($directory . DIRECTORY_SEPARATOR . '.portix-write-test-*');
        if ($leftovers !== false && $leftovers !== []) {
            throw new RuntimeException('Un fichier de contrôle temporaire subsiste dans ' . $relative . '.');
        }
    }

    $blockedRoot = $root . DIRECTORY_SEPARATOR . 'blocked';
    if (!mkdir($blockedRoot, 0770, true) && !is_dir($blockedRoot)) {
        throw new RuntimeException('Impossible de préparer le cas d’échec.');
    }
    file_put_contents($blockedRoot . DIRECTORY_SEPARATOR . 'database', 'not-a-directory');
    $blocked = (new Portix\Installation\EnvironmentPreflight($blockedRoot))->run();
    $databaseCheck = null;
    foreach ($blocked['directories'] as $check) {
        if ($check['label'] === 'database/') {
            $databaseCheck = $check;
            break;
        }
    }
    if ($databaseCheck === null || $databaseCheck['ok']) {
        throw new RuntimeException('Le précontrôle doit refuser un chemin database qui n’est pas un dossier.');
    }

    $projectRoot = dirname(__DIR__);
    $contentFile = $projectRoot . '/src/Installation/default-content.json';
    $content = json_decode((string) file_get_contents($contentFile), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($content) || !isset($content['articles'], $content['news'], $content['categories'])) {
        throw new RuntimeException('Le contenu initial distribué est absent ou invalide.');
    }
    $articleTitles = array_map(
        static fn (array $article): string => (string) ($article['title'] ?? ''),
        $content['articles']
    );
    foreach ([
        'Présentation',
        'Installation de Portix 3',
        'Administration de Portix 3',
        'Guide du rédacteur de Portix 3',
        'Portix 3 — Structure et fonctionnement',
        'Développer un module',
        'Développer un module avec l’aide d’une IA',
    ] as $expectedTitle) {
        if (!in_array($expectedTitle, $articleTitles, true)) {
            throw new RuntimeException('Article initial manquant : ' . $expectedTitle);
        }
    }
    $newsTitles = array_map(
        static fn (array $item): string => (string) ($item['title'] ?? ''),
        $content['news']
    );
    foreach (['Félicitation !', 'Et maintenant ?'] as $expectedTitle) {
        if (!in_array($expectedTitle, $newsTitles, true)) {
            throw new RuntimeException('Actualité initiale manquante : ' . $expectedTitle);
        }
    }
    $htmlBlocks = [];
    foreach ($content['articles'] as $article) {
        $htmlBlocks[] = (string) ($article['body'] ?? '');
    }
    foreach ($content['news'] as $item) {
        $htmlBlocks[] = (string) ($item['intro'] ?? '');
        $htmlBlocks[] = (string) ($item['body'] ?? '');
    }
    foreach ($htmlBlocks as $html) {
        if (preg_match_all('~var/uploads/images/([A-Za-z0-9._-]+)~', $html, $matches) === false) {
            throw new RuntimeException('Impossible de contrôler les images du contenu initial.');
        }
        foreach ($matches[1] as $fileName) {
            if (!is_file($projectRoot . '/var/uploads/images/' . $fileName)) {
                throw new RuntimeException('Image du contenu initial manquante : ' . $fileName);
            }
        }
    }

    echo "Installateur : dossiers, permissions et contenu initial distribué : OK\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Test installateur : ÉCHEC - ' . $error->getMessage() . "\n");
    $status = 1;
} finally {
    if (is_dir($root)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($root);
    }
}

exit($status);
