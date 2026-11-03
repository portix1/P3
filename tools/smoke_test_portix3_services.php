<?php
declare(strict_types=1);

use Portix\Infrastructure\Database;
use Portix\Infrastructure\Migrator;
use Portix\Module\ModuleInstaller;
use Portix\Observability\EventLogger;
use Portix\Repository\RepositoryRegistry;
use Portix\Seo\PageMetadata;
use Portix\Storage\DownloadStorage;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Ce test doit être exécuté en ligne de commande.\n");
    exit(1);
}

$root = dirname(__DIR__);
require $root . '/bootstrap/autoload.php';
require $root . '/includes/class/security.class.php';
$temporaryRoot = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR
    . 'portix3-services-smoke-' . getmypid() . '-' . bin2hex(random_bytes(4));
$database = null;
$status = 0;

try {
    if (!mkdir($temporaryRoot, 0770, true)) {
        throw new RuntimeException('Impossible de créer le dossier temporaire.');
    }
    $database = new Database($temporaryRoot . '/database/portix2.sqlite');
    (new Migrator($database))->migrate();
    $repositories = new RepositoryRegistry($database);
    $installer = new ModuleInstaller($database, $repositories->modules());
    foreach (['contact', 'download', 'insights'] as $module) {
        $installer->installDirectory($root . '/var/modules/' . $module);
    }

    $conversationId = $repositories->contact()->createConversation(
        'Alice Exemple',
        'alice@example.test',
        'Demande de renseignements',
        'Bonjour, ceci est un test.'
    );
    $repositories->contact()->addMessage(
        $conversationId,
        'outgoing',
        'Merci pour votre message.',
        'admin',
        'sent'
    );
    serviceAssertSame(2, count($repositories->contact()->messages($conversationId)), 'fil de conversation');
    $repositories->contact()->setStatus($conversationId, 'closed');
    serviceAssertSame('closed', $repositories->contact()->findConversation($conversationId)['status'], 'archivage Contact');

    $insights = $repositories->insights();
    $today = gmdate('Y-m-d');
    $insights->recordView($today, 'accueil', 'Accueil — Portix 3', 'direct', 'desktop', false, 'visitor-a');
    $insights->recordView($today, 'accueil', 'Accueil — Portix 3', 'direct', 'desktop', false, 'visitor-a');
    $insights->recordView($today, 'robots', 'Robot', 'direct', 'desktop', true);
    serviceAssertSame(
        ['page_views' => 2, 'visits_estimated' => 1, 'bot_views' => 1],
        $insights->totals(7),
        'agrégation de l’audience'
    );
    serviceAssertSame(1, count($insights->topPages(7)), 'classement des pages');
    $emptyDailyMaximum = max(array_merge(
        [1],
        array_map(static fn (array $row): int => (int) $row['page_views'], [])
    ));
    serviceAssertSame(1, $emptyDailyMaximum, 'graphique vide des statistiques');
    $logger = new EventLogger($database->connection());
    $logger->application('warning', 'test.warning', 'Test technique', ['token' => 'secret'], 'request-test');
    $logger->audit('admin', 'test.audit', 'service', [], 'request-test');
    serviceAssertSame(1, count($insights->recentApplicationLogs()), 'journal technique');
    serviceAssertSame(1, count($insights->recentAuditLogs()), 'journal d’audit');
    serviceAssertTrue(
        !str_contains((string) $insights->recentApplicationLogs()[0]['context_json'], 'secret'),
        'secret exclu du journal'
    );

    $categoryId = $repositories->downloads()->createCategory('Guides', 'guides', 'Documentation');
    $source = $temporaryRoot . '/guide.pdf';
    file_put_contents($source, "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF\n");
    $storage = new DownloadStorage($temporaryRoot . '/private-downloads');
    $stored = $storage->storeLocalFile($source, 'Guide Portix.pdf');
    $downloadId = $repositories->downloads()->create([
        ...$stored,
        'category_id' => $categoryId,
        'title' => 'Guide Portix',
        'description' => 'Guide de test',
        'status' => 'published',
    ]);
    $download = $repositories->downloads()->find($downloadId, true);
    serviceAssertSame('application/pdf', $download['mime_type'], 'détection du type réel');
    serviceAssertTrue(is_file($storage->path((string) $download['stored_name'])), 'stockage privé');
    $repositories->downloads()->incrementCounter($downloadId);
    serviceAssertSame(1, (int) $repositories->downloads()->find($downloadId)['download_count'], 'compteur de téléchargement');
    $storage->delete((string) $download['stored_name']);
    $repositories->downloads()->delete($downloadId);

    $metadata = new PageMetadata(
        'Portix 3',
        'CMS moderne',
        'https://example.test',
        'Alice Exemple',
        'Portix Project',
        'var/uploads/images/logo.webp'
    );
    $metadata->define(
        'Guide',
        '<p>Résumé du guide</p>',
        'guide.html',
        'article',
        '2026-08-31 10:00:00',
        '2026-09-18 09:00:00',
        '',
        'https://cdn.example.test/guide.webp',
        'TechArticle'
    );
    $metadata->breadcrumbs([['name' => 'Guides', 'url' => 'https://example.test/guides.html']]);
    $jsonLd = $metadata->jsonLd();
    serviceAssertSame('Guide — Portix 3', $metadata->documentTitle(), 'titre de document');
    serviceAssertSame('https://example.test/guide.html', $metadata->canonicalUrl(), 'URL canonique');
    serviceAssertTrue(str_contains($jsonLd, 'BreadcrumbList'), 'fil d’Ariane structuré');
    serviceAssertTrue(str_contains($jsonLd, 'TechArticle'), 'type TechArticle');
    serviceAssertTrue(str_contains($jsonLd, 'dateModified'), 'date de modification structurée');
    serviceAssertTrue(str_contains($jsonLd, 'Alice Exemple'), 'auteur structuré');
    serviceAssertTrue(str_contains($jsonLd, 'Portix Project'), 'éditeur structuré');
    serviceAssertTrue(str_contains($jsonLd, 'https://example.test/var/uploads/images/logo.webp'), 'logo structuré');
    serviceAssertTrue(str_contains($jsonLd, 'https://cdn.example.test/guide.webp'), 'image structurée absolue');
    serviceAssertSame('var/uploads/images/a.webp', PageMetadata::firstImageFromHtml('<p>x</p><img src="var/uploads/images/a.webp" alt="">'), 'première image HTML');
    $metadata->noIndexNoFollow();
    serviceAssertSame('noindex,nofollow', $metadata->robots(), 'robots de prévisualisation');

    serviceAssertSame('ok', $database->connection()->query('PRAGMA integrity_check')->fetchColumn(), 'intégrité SQLite');
    serviceAssertSame([], $database->connection()->query('PRAGMA foreign_key_check')->fetchAll(), 'clés étrangères');
    echo "Contact, statistiques, journaux, téléchargements et métadonnées : OK\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Test des services Portix 3 : ÉCHEC - ' . $error->getMessage() . "\n");
    $status = 1;
} finally {
    unset($metadata, $storage, $logger, $insights, $installer, $repositories);
    if ($database instanceof Database) {
        $database->close();
    }
    unset($database);
    gc_collect_cycles();
    serviceRemoveTree($temporaryRoot);
}
exit($status);

function serviceAssertTrue(bool $condition, string $label): void
{
    if (!$condition) {
        throw new RuntimeException($label . ' : échec.');
    }
}

function serviceAssertSame(mixed $expected, mixed $actual, string $label): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $label . ' : attendu ' . var_export($expected, true)
            . ', obtenu ' . var_export($actual, true)
        );
    }
}

function serviceRemoveTree(string $directory): void
{
    if (!is_dir($directory) || !str_starts_with(basename($directory), 'portix3-services-smoke-')) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($directory);
}
