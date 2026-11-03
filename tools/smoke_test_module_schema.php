<?php
declare(strict_types=1);

use Portix\Infrastructure\Database;
use Portix\Infrastructure\Migrator;
use Portix\Module\ModuleInstaller;
use Portix\Module\PublicRouteResolver;
use Portix\Repository\RepositoryRegistry;
use Portix\Support\Translator;
use PortixModule\Articles\ArticleNavigation;
use PortixModule\Articles\ArticleRepository;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Ce test doit être exécuté en ligne de commande.\n");
    exit(1);
}

$root = dirname(__DIR__);
require $root . '/bootstrap/autoload.php';
require $root . '/var/modules/articles/src/ArticleRepository.php';
require $root . '/var/modules/articles/src/ArticleNavigation.php';

$temporaryRoot = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR
    . 'portix2-module-schema-smoke-' . getmypid() . '-' . bin2hex(random_bytes(4));
$database = null;
$pdo = null;
$status = 0;

// Release every repository/statement before deleting SQLite files (also on Windows).
$status = (static function () use ($root, $temporaryRoot): int {
$database = null;
$pdo = null;
$status = 0;

try {
    if (!mkdir($temporaryRoot, 0770, true)) {
        throw new RuntimeException('Impossible de créer le dossier temporaire.');
    }
    $database = new Database($temporaryRoot . '/database/portix2.sqlite');
    (new Migrator($database))->migrate();
    $repositories = new RepositoryRegistry($database);
    $installer = new ModuleInstaller($database, $repositories->modules());

    $schemaFiles = glob($root . '/var/modules/*/database/*/desc.csv') ?: [];
    assertTrue(
        $schemaFiles !== [],
        'la distribution contient des schémas modulaires installables'
    );
    foreach ($schemaFiles as $schemaFile) {
        assertTrue(
            is_file($schemaFile) && filesize($schemaFile) > 0,
            'schéma modulaire présent et non vide : '
                . str_replace('\\', '/', substr($schemaFile, strlen($root) + 1))
        );
    }

    $downloadResult = $installer->installDirectory($root . '/var/modules/download');
    assertSameValue(['download_categories', 'downloads'], $downloadResult['tables'], 'schéma du gestionnaire de fichiers');
    $contactResult = $installer->installDirectory($root . '/var/modules/contact');
    assertSameValue(['contact_conversations', 'contact_messages'], $contactResult['tables'], 'schéma de la messagerie Contact');
    $insightsResult = $installer->installDirectory($root . '/var/modules/insights');
    assertSameValue(
        ['analytics_audience_daily', 'analytics_collection_daily', 'analytics_confirmed_daily', 'analytics_confirmed_visitors', 'analytics_daily', 'analytics_devices', 'analytics_pages', 'analytics_referrers', 'analytics_unique_visitors', 'analytics_visitor_days', 'analytics_visitors', 'application_logs', 'audit_logs'],
        $insightsResult['tables'],
        'schéma des statistiques et journaux'
    );

    $result = $installer->installDirectory($root . '/var/modules/articles');
    assertSameValue(['article_categories', 'article_reading_options', 'article_relations', 'article_series', 'article_series_parts', 'article_settings', 'articles'], $result['tables'], 'tables installées');
    assertTrue(tableExists($database->connection(), 'article_categories'), 'table des catégories');
    assertTrue(tableExists($database->connection(), 'article_settings'), 'table des réglages du menu');
    assertTrue(tableExists($database->connection(), 'articles'), 'table des articles');
    assertSameValue(
        count($result['tables']),
        (int) $database->connection()->query(
            "SELECT COUNT(*) FROM module_schema_tables WHERE module_prefix = 'articles'"
        )->fetchColumn(),
        'propriété des tables'
    );
    assertSameValue('editor', (string) $result['module']['admin_access'], 'accès rédacteur');
    assertSameValue('desc-csv', (string) $result['module']['schema_format'], 'format de schéma déclaré');
    assertSameValue('navigation.php', (string) $result['module']['public_navigation'], 'navigation publique déclarée');
    assertSameValue('routes.php', (string) $result['module']['public_route_resolver'], 'résolveur d’URL déclaré');
    assertSameValue('sitemap.php', (string) $result['module']['public_sitemap'], 'sitemap public déclaré');
    assertTrue(
        in_array('position', array_column($database->connection()->query('PRAGMA table_info(articles)')->fetchAll(), 'name'), true),
        'position éditoriale des articles'
    );
    assertTrue(
        in_array('is_visible', array_column($database->connection()->query('PRAGMA table_info(article_categories)')->fetchAll(), 'name'), true),
        'visibilité publique des catégories'
    );

    $articleRepository = new ArticleRepository($database->connection());
    $articleColumns = array_column(
        $database->connection()->query('PRAGMA table_info(articles)')->fetchAll(),
        'name'
    );
    assertTrue(in_array('author_name', $articleColumns, true), 'colonne auteur des articles');
    assertTrue(in_array('featured_image', $articleColumns, true), 'colonne image principale des articles');
    assertSameValue(
        ['header_enabled' => true, 'show_articles' => true, 'navigation_mode' => 'book'],
        $articleRepository->menuSettings(),
        'réglages par défaut du menu'
    );
    $guidesId = $articleRepository->createCategory('Guides', 'guides', null);
    $companyId = $articleRepository->createCategory('Entreprise', 'entreprise', null);
    $installationId = $articleRepository->createCategory('Installation', 'installation', $guidesId);
    $usageId = $articleRepository->createCategory('Utilisation', 'utilisation', $guidesId);
    $advancedId = $articleRepository->createCategory('Approfondir', 'approfondir', $installationId);
    $now = '2026-08-30 12:00:00';
    $createArticle = static function (ArticleRepository $repository, int $categoryId, string $title, string $slug) use ($now): int {
        return $repository->createArticle(
            $categoryId,
            $title,
            $slug,
            '<p>Contenu</p>',
            'published',
            'admin',
            $now,
            $now
        );
    };
    $guideArticleId = $createArticle($articleRepository, $guidesId, 'Commencer', 'commencer');
    assertSameValue('', (string) $articleRepository->find($guideArticleId)['summary'], 'résumé d’article désactivé');
    $installFirstId = $createArticle($articleRepository, $installationId, 'Installer', 'installer');
    $installSecondId = $createArticle($articleRepository, $installationId, 'Configurer', 'configurer');
    $advancedArticleId = $createArticle($articleRepository, $advancedId, 'Approfondir', 'aller-plus-loin');
    $usageArticleId = $createArticle($articleRepository, $usageId, 'Utiliser', 'utiliser');
    $companyArticleId = $createArticle($articleRepository, $companyId, 'Notre société', 'notre-societe');

    $articleRepository->reorderContent(
        [
            'root' => [$companyId, $guidesId],
            (string) $guidesId => [$usageId, $installationId],
            (string) $installationId => [$advancedId],
        ],
        [
            (string) $guidesId => [$guideArticleId],
            (string) $installationId => [$installSecondId, $installFirstId],
            (string) $advancedId => [$advancedArticleId],
            (string) $usageId => [$usageArticleId],
            (string) $companyId => [$companyArticleId],
        ]
    );
    assertSameValue(
        [$companyId, $guidesId],
        array_map(
            static fn (array $category): int => (int) $category['id'],
            array_values(array_filter(
                $articleRepository->categories(),
                static fn (array $category): bool => $category['parent_id'] === null
            ))
        ),
        'ordre des catégories principales'
    );
    assertSameValue(
        [$guideArticleId, $usageArticleId, $installSecondId, $installFirstId, $advancedArticleId],
        array_map('intval', array_column($articleRepository->published($guidesId), 'id')),
        'ordre de lecture du livre'
    );
    assertSameValue(
        ['Guides', 'Installation', 'Approfondir'],
        array_column($articleRepository->categoryTrail($advancedId), 'name'),
        'fil d’Ariane complet'
    );
    assertSameValue(
        $installFirstId,
        (int) $articleRepository->publishedNeighbors($installSecondId, 'category')['next']['id'],
        'navigation dans la catégorie exacte'
    );
    $bookNeighbors = $articleRepository->publishedNeighbors($usageArticleId, 'book');
    assertSameValue($guideArticleId, (int) $bookNeighbors['previous']['id'], 'chapitre précédent du livre');
    assertSameValue($installSecondId, (int) $bookNeighbors['next']['id'], 'chapitre suivant du livre');
    assertSameValue(
        null,
        $articleRepository->publishedNeighbors($advancedArticleId, 'book')['next'],
        'le livre ne traverse pas la catégorie principale suivante'
    );
    assertSameValue(
        ['previous' => null, 'next' => null],
        $articleRepository->publishedNeighbors($companyArticleId, 'off'),
        'navigation séquentielle désactivée'
    );
    assertSameValue(
        $installFirstId,
        (int) $articleRepository->findPublishedBySlug('installer')['id'],
        'article publié'
    );
    assertSameValue(
        ['articles', 'page', 'installer'],
        (new PublicRouteResolver($root, $database->connection()))->resolve(
            'installer',
            [$result['module']]
        ),
        'URL publique courte résolue par le module'
    );
    assertSameValue(
        'installer-2',
        $repositories->news()->uniqueSlug('installer', [], null),
        'une actualité ne reprend pas l’URL d’un article'
    );
    $repositories->news()->create(
        'Annonce',
        'annonce',
        'admin',
        '<p>Résumé</p>',
        '<p>Contenu</p>',
        'published',
        $now,
        $now
    );
    assertSameValue(
        'annonce-2',
        $articleRepository->uniqueArticleSlug('annonce', [], null),
        'un article ne reprend pas l’URL d’une actualité'
    );
    assertSameValue(
        'annonce-2.html',
        $repositories->menu()->localUrlFromName('Annonce'),
        'un lien de menu sans URL évite les actualités et les articles'
    );

    $navigationTemplateFile = $temporaryRoot . '/navigation.tpl';
    file_put_contents(
        $navigationTemplateFile,
        '{% for navigation in navigation %}{{ navigation.name }}{{ navigation.submenu }}{% endfor %}'
    );
    $navigation = new ArticleNavigation(
        $articleRepository,
        new Translator(['articles_menu_toggle' => 'Ouvrir ou fermer %s'])
    );
    $navigationTemplate = new \Portix\View\Template($navigationTemplateFile);
    $navigation->append($navigationTemplate, ['articles', 'categorie', 'installation']);
    $navigationRows = $navigationTemplate->rows('navigation');
    assertSameValue(2, count($navigationRows), 'catégories principales dans le header');
    $guidesNavigation = array_values(array_filter(
        $navigationRows,
        static fn (array $row): bool => (string) $row['name'] === 'Guides'
    ))[0];
    assertSameValue('is-current', (string) $guidesNavigation['current'], 'branche active du menu');
    assertSameValue('has-submenu', (string) $guidesNavigation['item_class'], 'menu déroulant détecté');
    assertTrue(
        str_contains((string) $guidesNavigation['submenu'], 'articles-categorie-installation.html'),
        'sous-catégorie dans le menu'
    );
    assertTrue(
        str_contains((string) $guidesNavigation['submenu'], 'installer.html'),
        'article publié dans le menu'
    );
    $articleRouteTemplate = new \Portix\View\Template($navigationTemplateFile);
    $navigation->append($articleRouteTemplate, ['articles', 'page', 'installer']);
    $articleRouteGuides = array_values(array_filter(
        $articleRouteTemplate->rows('navigation'),
        static fn (array $row): bool => (string) $row['name'] === 'Guides'
    ))[0];
    assertSameValue(
        'is-current',
        (string) $articleRouteGuides['current'],
        'branche active depuis une URL d’article courte'
    );

    $articleRepository->saveMenuSettings(true, false, 'category');
    assertSameValue('category', $articleRepository->menuSettings()['navigation_mode'], 'mode de navigation enregistré');
    $categoryOnlyTemplate = new \Portix\View\Template($navigationTemplateFile);
    $navigation->append($categoryOnlyTemplate, ['articles']);
    $categoryOnlyRows = $categoryOnlyTemplate->rows('navigation');
    $categoryOnlyGuides = array_values(array_filter(
        $categoryOnlyRows,
        static fn (array $row): bool => (string) $row['name'] === 'Guides'
    ))[0];
    assertTrue(
        str_contains((string) $categoryOnlyGuides['submenu'], 'articles-categorie-installation.html'),
        'sous-catégorie conservée quand les articles sont masqués'
    );
    assertTrue(
        !str_contains((string) $categoryOnlyGuides['submenu'], 'installer.html'),
        'articles masqués par la configuration'
    );

    $hiddenId = $articleRepository->createCategory('Mode d’emploi privé', 'mode-emploi-prive', null, false);
    $hiddenArticleId = $createArticle(
        $articleRepository,
        $hiddenId,
        'Documentation privée',
        'documentation-privee'
    );
    assertSameValue(
        $hiddenArticleId,
        (int) $articleRepository->findPublishedBySlug('documentation-privee')['id'],
        'article d’une catégorie masquée accessible par son URL'
    );
    assertSameValue(
        ['articles', 'page', 'documentation-privee'],
        (new PublicRouteResolver($root, $database->connection()))->resolve(
            'documentation-privee',
            [$result['module']]
        ),
        'URL courte d’un article masqué de la navigation'
    );
    assertSameValue(
        $hiddenId,
        (int) $articleRepository->findCategoryBySlug('mode-emploi-prive')['id'],
        'catégorie masquée accessible par son URL'
    );
    assertSameValue(
        [$hiddenArticleId],
        array_map('intval', array_column($articleRepository->published($hiddenId, true), 'id')),
        'contenu d’une catégorie masquée accessible directement'
    );
    assertTrue(
        !in_array($hiddenArticleId, array_map('intval', array_column($articleRepository->published(), 'id')), true),
        'article d’une catégorie masquée absent de la liste générale'
    );
    assertTrue(
        !in_array($hiddenId, array_map('intval', array_column($articleRepository->visibleCategories(), 'id')), true),
        'catégorie masquée absente des listes publiques'
    );
    $hiddenNavigationTemplate = new \Portix\View\Template($navigationTemplateFile);
    $articleRepository->saveMenuSettings(true, true, 'book');
    $navigation->append($hiddenNavigationTemplate, ['articles']);
    assertTrue(
        !str_contains($hiddenNavigationTemplate->render(), 'Mode d’emploi privé'),
        'catégorie masquée absente du menu'
    );
    $articleRepository->updateArticleStatus($hiddenArticleId, 'draft', $now);
    assertSameValue('draft', (string) $articleRepository->find($hiddenArticleId)['status'], 'passage rapide en brouillon');
    $articleRepository->updateArticleStatus($hiddenArticleId, 'published', $now);
    assertSameValue('published', (string) $articleRepository->find($hiddenArticleId)['status'], 'publication rapide');

    $articleRepository->saveMenuSettings(false, true, 'book');
    $disabledTemplate = new \Portix\View\Template($navigationTemplateFile);
    $navigation->append($disabledTemplate, ['articles']);
    assertSameValue([], $disabledTemplate->rows('navigation'), 'menu désactivé');
    $articleRepository->saveMenuSettings(true, true, 'book');

    $articleRepository->deleteCategory($advancedId);
    assertSameValue(
        null,
        $articleRepository->find($advancedArticleId)['category_id'],
        'suppression de catégorie sans perte d’article'
    );
    assertSameValue([], $database->connection()->query('PRAGMA foreign_key_check')->fetchAll(), 'clés étrangères');

    // Reinstalling the same module is intentionally idempotent.
    $installer->installDirectory($root . '/var/modules/articles');
    assertSameValue('ok', $database->connection()->query('PRAGMA integrity_check')->fetchColumn(), 'intégrité SQLite');

    $evil = $temporaryRoot . '/evil';
    if (!mkdir($evil . '/database/users', 0770, true)) {
        throw new RuntimeException('Impossible de créer le faux module.');
    }
    file_put_contents(
        $evil . '/desc.xml',
        '<?xml version="1.0"?><modules><Name>Collision</Name><Prefix>evil</Prefix>'
        . '<Type>module</Type><Priority>99</Priority><DefLang>fr</DefLang>'
        . '<Path>evil</Path><Enabled>1</Enabled><SchemaFormat>desc-csv</SchemaFormat></modules>'
    );
    file_put_contents($evil . '/database/users/desc.csv', "id; numeric; notnull; ; ; ; primary; ;\n");
    try {
        $installer->installDirectory($evil);
        throw new RuntimeException('Une redéfinition de la table users a été acceptée.');
    } catch (RuntimeException $error) {
        assertSameValue('modules_schema_table_conflict', $error->getMessage(), 'collision avec le noyau');
    }
    assertSameValue(null, $repositories->modules()->findByPrefix('evil'), 'transaction annulée');

    echo "Schémas SQLite modulaires et module Articles : OK\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Test des schémas modulaires : ÉCHEC - ' . $error->getMessage() . "\n");
    $status = 1;
}
return $status;
})();
gc_collect_cycles();
removeModuleTestTree($temporaryRoot);

exit($status);

function tableExists(PDO $pdo, string $name): bool
{
    $statement = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = :name");
    $statement->execute(['name' => $name]);
    return $statement->fetchColumn() !== false;
}

function assertTrue(bool $condition, string $label): void
{
    if (!$condition) {
        throw new RuntimeException($label . ' : échec.');
    }
}

function assertSameValue(mixed $expected, mixed $actual, string $label): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $label . ' : attendu ' . var_export($expected, true)
            . ', obtenu ' . var_export($actual, true)
        );
    }
}

function removeModuleTestTree(string $directory): void
{
    if (!is_dir($directory) || !str_starts_with(basename($directory), 'portix2-module-schema-smoke-')) {
        return;
    }
    for ($attempt = 0; $attempt < 10 && is_dir($directory); $attempt++) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($directory);
        if (is_dir($directory)) {
            usleep(100000);
        }
    }
}
