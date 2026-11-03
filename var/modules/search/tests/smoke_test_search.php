<?php
declare(strict_types=1);

$projectRoot = dirname(__DIR__, 4);
$moduleRoot = dirname(__DIR__);
$sourceFile = $projectRoot . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'portix2.sqlite';

if (!extension_loaded('pdo_sqlite')) {
    fwrite(STDERR, "PDO_SQLITE absent.\n");
    exit(2);
}
if (!is_file($sourceFile)) {
    fwrite(STDERR, "Base Portix3 introuvable.\n");
    exit(3);
}

require_once $moduleRoot . '/src/SearchRepository.php';

$source = new PDO('sqlite:' . $sourceFile, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$testIndex = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'portix-search-test-' . bin2hex(random_bytes(5)) . '.sqlite';

try {
    $repository = new \PortixModule\Search\SearchRepository($source, $testIndex);
    $sync = $repository->sync(true);

    $expectedArticles = (int) $source->query(
        "SELECT COUNT(*) FROM articles WHERE status = 'published'"
    )->fetchColumn();
    $expectedNews = (int) $source->query(
        "SELECT COUNT(*) FROM news WHERE status = 'published'"
    )->fetchColumn();

    if ((int) $sync['articles'] !== $expectedArticles || (int) $sync['news'] !== $expectedNews) {
        throw new RuntimeException('Comptage des contenus incohérent.');
    }

    $title = (string) $source->query(
        "SELECT title FROM articles WHERE status = 'published' ORDER BY id LIMIT 1"
    )->fetchColumn();
    preg_match('/[\p{L}\p{N}]{3,}/u', $title, $match);
    if (($match[0] ?? '') !== '') {
        $results = $repository->search((string) $match[0], 'article', 10);
        if ($results === []) {
            throw new RuntimeException('La recherche de contrôle ne retourne aucun article.');
        }
    }

    echo 'Search module : OK — mode ' . $sync['mode']
        . ', articles ' . $sync['articles']
        . ', news ' . $sync['news'] . PHP_EOL;
} finally {
    @unlink($testIndex);
    @unlink($testIndex . '-shm');
    @unlink($testIndex . '-wal');
}
