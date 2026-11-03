<?php
declare(strict_types=1);

use Portix\Infrastructure\Database;
use Portix\Infrastructure\Migrator;
use Portix\Module\ModuleInstaller;
use Portix\Repository\RepositoryRegistry;
use Portix\Repository\ContentSafetyRepository;
use Portix\Seo\PageMetadata;
use Portix\Support\Translator;
use Portix\View\Template;
use Portix\Domain\UserAccount;
use PortixModule\Articles\ArticleRepository;
use PortixModule\Articles\ArticleAdminController;
use PortixModule\Articles\CategoryMetadataSchema;

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
chdir($root);
require $root . '/bootstrap/autoload.php';
require $root . '/var/modules/articles/src/ArticleRepository.php';
require $root . '/var/modules/articles/src/ArticleAdminController.php';
require $root . '/var/modules/articles/src/CategoryMetadataSchema.php';
require $root . '/includes/class/security.class.php';
require $root . '/includes/class/settings.class.php';

function categoryCheck(bool $condition, string $label): void
{
    if (!$condition) { throw new RuntimeException($label); }
}

try {
    $database = new Database(':memory:');
    (new Migrator($database))->migrate();
    $repositories = new RepositoryRegistry($database);
    (new ModuleInstaller($database, $repositories->modules()))->installDirectory($root . '/var/modules/articles');
    $repo = new ArticleRepository($database->connection());
    $description = 'Bois, forme et confort : comprendre le corps d’une guitare.';
    $id = $repo->createCategory('Le corps', 'le-corps', null, true, $description);
    $body = '<p>Texte intact.</p><img src="schema.webp" alt="Schéma" />';
    $repo->createArticle($id, 'La table', 'la-table', $body, 'published', 'admin', '2026-10-08', '2026-10-08');

    // Each form scenario runs separately because a successful controller redirects and exits.
    $formCase = $argv[1] ?? '';
    if ($formCase !== '') {
        $_LANG = [];
        require $root . '/var/modules/articles/lang/fr.php';
        $_LANG += ['ui_save' => 'Enregistrer', 'ui_cancel' => 'Annuler', 'security_invalid_csrf' => 'Jeton de formulaire invalide.'];
        $translator = new Translator($_LANG);
        $template = new Template($root . '/var/modules/articles/templates/admin-category-form.tpl');
        foreach ($_LANG as $key => $value) { $template->set($key, $value); }
        $template->set('csrf_token', 'test-category-token');
        $user = new UserAccount(['pseudo' => 'test', 'is_admin' => false, 'is_redactor' => true]);
        $_SESSION['csrf_token'] = 'test-category-token';
        $_SERVER['REQUEST_METHOD'] = $formCase === 'form' ? 'GET' : 'POST';
        $_POST = ['csrf_token' => 'test-category-token', 'name' => 'Le corps', 'slug' => 'le-corps', 'is_visible' => '1'];
        $expected = $description;
        if ($formCase === 'valid') { $_POST['description'] = '<b>Description mise à jour.</b>'; $expected = 'Description mise à jour.'; }
        if ($formCase === 'clear') { $_POST['description'] = ''; $expected = ''; }
        if ($formCase === 'too-long') { $_POST['description'] = str_repeat('é', 171); }
        if ($formCase === 'boundary') { $_POST['description'] = str_repeat('é', 170); $expected = $_POST['description']; }
        if ($formCase === 'csrf') { $_POST['csrf_token'] = 'invalid'; $_POST['description'] = 'Forbidden change'; }
        if ($formCase === 'create') { $_POST['description'] = 'Nouvelle catégorie.'; $_POST['name'] = 'Autre'; $_POST['slug'] = 'autre'; }
        register_shutdown_function(static function () use ($repo, $id, $expected, $formCase): void {
            if (error_get_last() !== null && in_array(error_get_last()['type'], [E_ERROR, E_PARSE], true)) { return; }
            $saved = $formCase === 'create' ? $repo->findCategoryBySlug('autre') : $repo->findCategory($id);
            $expectedDescription = $formCase === 'create' ? 'Nouvelle catégorie.' : $expected;
            if ($saved === null || $saved['description'] !== $expectedDescription) {
                fwrite(STDERR, 'Formulaire catégorie : échec ' . $formCase . "\n"); exit(1);
            }
            echo 'Formulaire catégorie : ' . $formCase . " OK\n";
        });
        (new ArticleAdminController($repo, $root, $translator))->handle(
            $template, ['Admin', 'articles', 'category', $formCase === 'create' ? 'new' : 'edit', (string) $id], $user
        );
        $html = $template->render();
        if ($formCase === 'form') {
            categoryCheck(str_contains($html, 'name="description"') && str_contains($html, '170') && str_contains($html, 'aria-describedby="category-description-help"'), 'champ et aide accessibles');
        }
        if ($formCase === 'too-long') {
            categoryCheck(str_contains($html, $_LANG['articles_category_invalid_description']), 'erreur de longueur visible');
            categoryCheck(str_contains($html, str_repeat('é', 171)), 'brouillon conservé après erreur');
        }
        if ($formCase === 'csrf') { categoryCheck(str_contains($html, 'invalide'), 'refus CSRF visible'); }
        exit;
    }

    $repo->updateCategory($id, 'Le corps', 'le-corps', null);
    categoryCheck($repo->findCategory($id)['description'] === $description, 'appel ancien conserve la description');
    $repo->updateCategory($id, 'Le corps', 'le-corps', null, true, '');
    categoryCheck($repo->findCategory($id)['description'] === '', 'effacement explicite');
    $repo->updateCategory($id, 'Le corps', 'le-corps', null, true, $description);

    // Real category route, not only a stand-alone metadata object.
    $template = new Template($root . '/var/modules/articles/templates/index.tpl');
    $translator = new Translator(['articles_title' => 'Articles', 'articles_uncategorized' => 'Sans catégorie']);
    $breadcrumbTrail = new Portix\Navigation\BreadcrumbTrail('Accueil');
    $pageMetadata = new PageMetadata('Test', 'Description générale.', 'https://example.test');
    $argument = ['articles', 'categorie', 'le', 'corps'];
    require $root . '/var/modules/articles/index.php';
    categoryCheck($pageMetadata->description() === $description, 'description de la route publique');
    categoryCheck($pageMetadata->canonicalUrl() === 'https://example.test/articles-categorie-le-corps.html', 'URL canonique conservée');
    categoryCheck($pageMetadata->robots() === 'index,follow', 'catégorie publiée indexable');
    categoryCheck(!str_contains($template->render(), $description), 'description absente du contenu visible');
    $fallback = new PageMetadata('Test', 'Description générale.', 'https://example.test');
    $fallback->define('Le corps', '', 'articles-categorie-le-corps.html');
    categoryCheck($fallback->description() === 'Description générale.', 'description vide revient au site');

    $safety = new ContentSafetyRepository($database->connection());
    $snapshot = $safety->categorySnapshot($id);
    $safety->moveToTrash('article_category', $id, 'Le corps', $snapshot, 'test');
    $repo->deleteCategory($id);
    $safety->restoreTrash((int) $safety->trash()[0]['id']);
    categoryCheck($repo->findCategory($id)['description'] === $description, 'corbeille conserve la description');
    categoryCheck($repo->findPublishedBySlug('la-table')['body'] === $body, 'article et illustration conservés');
    unset($snapshot['description']);
    $safety->moveToTrash('article_category', $id, 'Le corps', $snapshot, 'test');
    $repo->deleteCategory($id);
    $safety->restoreTrash((int) $safety->trash()[0]['id']);
    categoryCheck($repo->findCategory($id)['description'] === '', 'ancienne corbeille restaurable');

    // Existing SQLite file: backup contains old schema and data, migration is repeatable.
    $tmp = sys_get_temp_dir() . '/p3-category-meta-' . bin2hex(random_bytes(6));
    mkdir($tmp . '/var/modules/articles/database', 0770, true);
    foreach (['articles', 'article_categories', 'article_settings'] as $table) {
        mkdir($tmp . '/var/modules/articles/database/' . $table, 0770, true);
        copy($root . '/var/modules/articles/database/' . $table . '/desc.csv', $tmp . '/var/modules/articles/database/' . $table . '/desc.csv');
    }
    $legacy = new Database($tmp . '/database.sqlite');
    (new Migrator($legacy))->migrate();
    $legacyRepositories = new RepositoryRegistry($legacy);
    (new ModuleInstaller($legacy, $legacyRepositories->modules()))->installDirectory($root . '/var/modules/articles');
    $legacyRepo = new ArticleRepository($legacy->connection());
    $legacyId = $legacyRepo->createCategory('Ancienne', 'ancienne', null);
    $legacy->connection()->exec('ALTER TABLE article_categories DROP COLUMN description');
    CategoryMetadataSchema::ensure($legacy, $tmp);
    categoryCheck($legacyRepo->findCategory($legacyId)['name'] === 'Ancienne', 'migration préserve les lignes');
    $backups = glob($tmp . '/var/private/category-description-backups/*.sqlite');
    categoryCheck(count($backups) === 1, 'sauvegarde avant migration');
    $backupPdo = new PDO('sqlite:' . $backups[0]);
    categoryCheck(!in_array('description', array_column($backupPdo->query('PRAGMA table_info(article_categories)')->fetchAll(PDO::FETCH_ASSOC), 'name'), true), 'sauvegarde du schéma initial');
    categoryCheck($backupPdo->query('SELECT name FROM article_categories')->fetchColumn() === 'Ancienne', 'sauvegarde des données');
    CategoryMetadataSchema::ensure($legacy, $tmp);
    categoryCheck(count(glob($tmp . '/var/private/category-description-backups/*.sqlite')) === 1, 'migration idempotente');
    categoryCheck($legacy->connection()->query('PRAGMA integrity_check')->fetchColumn() === 'ok', 'intégrité SQLite');
    $backupPdo = null; $legacyRepo = null; $legacyRepositories = null; $legacy->close(); $legacy = null;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $entry) { $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname()); }
    rmdir($tmp);
    echo "Descriptions de catégories : route publique, conservation, corbeille, sauvegarde et migration OK\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Test descriptions de catégories : ÉCHEC - ' . $error->getMessage() . "\n");
    exit(1);
}
