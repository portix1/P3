<?php
declare(strict_types=1);

use Portix\Http\Request;
use Portix\Infrastructure\Database;
use Portix\Infrastructure\Migrator;
use Portix\Module\ModuleInstaller;
use Portix\Repository\RepositoryRegistry;
use Portix\Seo\PublicUrl;
use Portix\Seo\SitemapGenerator;
use PortixModule\Articles\ArticleRepository;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Ce test doit être exécuté en ligne de commande.\n");
    exit(1);
}

$root = dirname(__DIR__);
require $root . '/bootstrap/autoload.php';
require $root . '/var/modules/articles/src/ArticleRepository.php';
$temporaryRoot = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR
    . 'portix3-sitemap-smoke-' . getmypid() . '-' . bin2hex(random_bytes(4));
$originalServer = $_SERVER;
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
    foreach (['news', 'articles', 'contact', 'download'] as $module) {
        $installer->installDirectory($root . '/var/modules/' . $module);
    }

    $now = '2026-09-16 12:30:00';
    $repositories->news()->create(
        'Actualité publiée', 'actualite-publiee', 'admin', '', '', 'published', $now, $now
    );
    $repositories->news()->create(
        'Actualité brouillon', 'actualite-brouillon', 'admin', '', '', 'draft', null, $now
    );

    $articles = new ArticleRepository($database->connection());
    $visibleCategory = $articles->createCategory('Guides', 'guides', null, true);
    $hiddenCategory = $articles->createCategory('Privé du menu', 'prive-menu', null, false);
    $articles->createCategory('Catégorie vide', 'categorie-vide', null, true);
    $articles->createArticle(
        $visibleCategory, 'Article publié', 'article-publie', '<p>Test</p>', 'published', 'admin', $now, $now
    );
    $articles->createArticle(
        $hiddenCategory, 'Article direct', 'article-direct', '<p>Test</p>', 'published', 'admin', $now, $now
    );
    $articles->createArticle(
        $visibleCategory, 'Article brouillon', 'article-brouillon', '<p>Test</p>', 'draft', 'admin', null, $now
    );

    $downloadCategory = $repositories->downloads()->createCategory('Fichiers', 'fichiers', 'Documents');
    $repositories->downloads()->create([
        'category_id' => $downloadCategory,
        'title' => 'Document publié',
        'original_name' => 'document.pdf',
        'stored_name' => 'document-test.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 10,
        'sha256' => str_repeat('a', 64),
        'description' => '',
        'status' => 'published',
    ]);

    $repositories->menu()->create('Accueil', '', 'accueil.html');
    $repositories->menu()->create('Page locale', '', 'page-locale.html#contenu');
    $repositories->menu()->create('Connexion', '', 'login.html');
    $repositories->menu()->create('Administration', '', 'Admin.html');
    $repositories->menu()->create('Externe', '', 'https://example.org/externe.html');

    $xml = (new SitemapGenerator($root, $database->connection()))->render(
        'https://example.test/P3',
        $repositories->modules()->allOrdered(),
        $repositories->menu()->allOrdered(),
        'news.html'
    );
    $document = new DOMDocument();
    sitemapAssert($document->loadXML($xml), 'XML du sitemap invalide');
    $xpath = new DOMXPath($document);
    $xpath->registerNamespace('s', 'http://www.sitemaps.org/schemas/sitemap/0.9');
    $locations = [];
    foreach ($xpath->query('//s:url/s:loc') ?: [] as $node) {
        $locations[] = (string) $node->textContent;
    }

    sitemapAssert(
        in_array('https://example.test/P3/', $locations, true),
        'URL canonique de l’accueil absente'
    );
    foreach ([
        'actualite-publiee.html',
        'articles.html',
        'articles-categorie-guides.html',
        'articles-categorie-prive-menu.html',
        'article-publie.html',
        'article-direct.html',
        'contact.html',
        'dwl.html',
        'dwl-cat-fichiers.html',
        'page-locale.html',
    ] as $path) {
        sitemapAssert(
            in_array('https://example.test/P3/' . $path, $locations, true),
            'URL publique absente : ' . $path
        );
    }
    foreach (['accueil.html', 'news.html', 'actualite-brouillon.html', 'article-brouillon.html', 'articles-categorie-categorie-vide.html', 'login.html', 'Admin.html'] as $path) {
        sitemapAssert(
            !in_array('https://example.test/P3/' . $path, $locations, true),
            'URL privée ou non publiée présente : ' . $path
        );
    }
    sitemapAssert(
        count(array_filter($locations, static fn (string $url): bool => str_contains($url, 'example.org'))) === 0,
        'lien externe présent dans le sitemap'
    );
    sitemapAssert(count($locations) === count(array_unique($locations)), 'URL dupliquée dans le sitemap');
    sitemapAssert(str_contains($xml, '<lastmod>2026-09-16</lastmod>'), 'date de modification absente');

    $_SERVER = [
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => '/P3/sitemap.xml',
        'SCRIPT_NAME' => '/P3/index.php',
        'HTTP_HOST' => 'localhost:8080',
    ];
    $request = Request::fromGlobals();
    sitemapAssert($request->isSitemap(), 'détection de sitemap.xml');
    sitemapAssert(!$request->isRobotsFile(), 'distinction sitemap/robots');
    sitemapAssert(
        PublicUrl::resolve('', $request) === 'http://localhost:8080/P3',
        'détection locale de l’adresse publique'
    );
    sitemapAssert(
        PublicUrl::resolve('https://example.test/site', $request) === 'https://example.test/site',
        'adresse publique configurée prioritaire'
    );
    $_SERVER['REQUEST_URI'] = '/P3/robots.txt';
    sitemapAssert(Request::fromGlobals()->isRobotsFile(), 'détection de robots.txt');
    foreach (['.htaccess', 'web.config'] as $rewriteFile) {
        $rewriteRules = (string) file_get_contents($root . '/' . $rewriteFile);
        sitemapAssert(
            str_contains($rewriteRules, 'sitemap') && str_contains($rewriteRules, 'robots'),
            'réécriture sitemap/robots absente de ' . $rewriteFile
        );
    }

    echo "Sitemap XML dynamique et robots.txt : OK\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Test sitemap : ÉCHEC - ' . $error->getMessage() . "\n");
    $status = 1;
} finally {
    $_SERVER = $originalServer;
    unset($articles, $installer, $repositories);
    if ($database instanceof Database) {
        $database->close();
    }
    unset($database);
    gc_collect_cycles();
    sitemapRemoveTree($temporaryRoot);
}
exit($status);

function sitemapAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function sitemapRemoveTree(string $directory): void
{
    if (!is_dir($directory) || !str_starts_with(basename($directory), 'portix3-sitemap-smoke-')) {
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
