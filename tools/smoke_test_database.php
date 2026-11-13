<?php
declare(strict_types=1);

use Portix\Infrastructure\Database;
use Portix\Infrastructure\Migrator;
use Portix\Repository\RepositoryRegistry;

/** Création et utilisation du schéma SQLite natif de Portix 3 0.7.x. */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Ce test doit être exécuté en ligne de commande.\n");
    exit(1);
}

require dirname(__DIR__) . '/bootstrap/autoload.php';

$temporaryRoot = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR
    . 'portix2-database-smoke-' . getmypid() . '-' . bin2hex(random_bytes(4));
$database = null;
$pdo = null;
$status = 0;

try {
    $database = new Database($temporaryRoot . '/database/portix2.sqlite');
    (new Migrator($database))->migrate();
    $pdo = $database->connection();
    $repositories = new RepositoryRegistry($database);

    assertSameValue(9, (int) $pdo->query('PRAGMA user_version')->fetchColumn(), 'version du schéma');
    assertSameValue(9, (int) $pdo->query('SELECT MAX(version) FROM schema_migrations')->fetchColumn(), 'migration enregistrée');
    foreach (['users', 'modules', 'menu_items', 'news', 'rate_limits', 'content_revisions', 'content_trash'] as $table) {
        assertTrue(tableExists($pdo, $table), 'table native ' . $table);
    }
    foreach (['agenda_items', 'groups', 'group_memberships', 'downloads', 'contact_conversations', 'analytics_daily', 'articles', 'comments', 'image', 'theme', '__portix_meta', '__portix_schema'] as $table) {
        assertSameValue(false, tableExists($pdo, $table), 'absence de la table ' . $table);
    }

    $repositories->users()->create(
        'admin',
        password_hash('correct horse battery staple', PASSWORD_DEFAULT),
        'admin@example.test',
        'Admin',
        'Portix',
        true,
        true
    );
    $admin = $repositories->users()->find('admin');
    assertTrue($admin !== null && $admin->isAdmin() && $admin->isRedactor(), 'compte administrateur');
    assertSameValue(1, $admin?->authVersion(), 'version de session initiale');

    $repositories->users()->create(
        'editor',
        password_hash('correct horse battery staple', PASSWORD_DEFAULT),
        'editor@example.test',
        'Editor',
        'Test',
        false,
        true
    );
    assertSameValue(2, $repositories->users()->count(), 'création d’un rédacteur');
    assertSameValue(2, $repositories->users()->countRedactors(), 'comptage des rédacteurs');
    assertSameValue(1, count($repositories->users()->searchForAdministration('EDITOR@EXAMPLE')), 'recherche partielle insensible à la casse');
    assertSameValue(1, count($repositories->users()->searchForAdministration('', 'admin')), 'filtre des administrateurs');
    assertSameValue(2, count($repositories->users()->searchForAdministration('', 'redactor')), 'filtre des rédacteurs');
    assertSameValue(0, count($repositories->users()->searchForAdministration('editor', 'admin')), 'recherche combinée au filtre');
    $editorBeforePassword = $repositories->users()->find('editor');
    $repositories->users()->updatePassword(
        'editor',
        password_hash('another correct horse battery staple', PASSWORD_DEFAULT)
    );
    $editorAfterPassword = $repositories->users()->find('editor');
    assertSameValue(
        ($editorBeforePassword?->authVersion() ?? 0) + 1,
        $editorAfterPassword?->authVersion(),
        'invalidation des sessions après changement de mot de passe'
    );

    $now = '2026-08-29 12:00:00';
    $newsId = $repositories->news()->create(
        'Nouvelle actualité',
        'nouvelle-actualite',
        'editor',
        'Introduction',
        '<p>Contenu</p>',
        'draft',
        null,
        $now
    );
    assertSameValue(null, $repositories->news()->findPublishedBySlug('nouvelle-actualite'), 'brouillon privé');
    $repositories->news()->update(
        $newsId,
        'Nouvelle actualité',
        'nouvelle-actualite',
        'Introduction',
        '<p>Contenu publié</p>',
        'published',
        $now,
        $now
    );
    assertSameValue($newsId, $repositories->news()->findPublishedBySlug('nouvelle-actualite')['id'], 'publication par slug');
    $repositories->news()->delete($newsId);
    assertSameValue(null, $repositories->news()->find($newsId), 'suppression préparée');

    $legacy = new Database($temporaryRoot . '/legacy-v2/portix2.sqlite');
    $legacyPdo = $legacy->connection();
    $legacyPdo->exec('CREATE TABLE schema_migrations (version INTEGER PRIMARY KEY, applied_at TEXT NOT NULL)');
    $legacyPdo->exec("INSERT INTO schema_migrations VALUES (2, '2026-08-30 00:00:00')");
    $legacyPdo->exec('CREATE TABLE download_categories (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL UNIQUE, description TEXT NOT NULL DEFAULT \'\')');
    $legacyPdo->exec('CREATE TABLE downloads (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL, stored_name TEXT NOT NULL, description TEXT NOT NULL DEFAULT \'\', download_count INTEGER NOT NULL DEFAULT 0, category_id INTEGER, FOREIGN KEY (category_id) REFERENCES download_categories(id) ON DELETE SET NULL)');
    $legacyPdo->exec("INSERT INTO download_categories (name, description) VALUES ('Congés annuels', 'Documents')");
    $legacyPdo->exec("INSERT INTO downloads (title, stored_name, description, download_count, category_id) VALUES ('Guide.pdf', 'legacy.pdf', 'Guide', 4, 1)");
    (new Migrator($legacy))->migrate();
    assertSameValue(9, (int) $legacyPdo->query('PRAGMA user_version')->fetchColumn(), 'migration directe depuis la version 2');
    assertSameValue('conges-annuels', (string) $legacyPdo->query('SELECT slug FROM download_categories')->fetchColumn(), 'slug des téléchargements migré');
    assertSameValue('dwl', (string) $legacyPdo->query("SELECT module_prefix FROM module_schema_tables WHERE table_name = 'downloads'")->fetchColumn(), 'propriété modulaire des téléchargements');
    $legacyPdo = null;
    $legacy->close();
    unset($legacy);

    $versionThree = new Database($temporaryRoot . '/legacy-v3/portix2.sqlite');
    $versionThreePdo = $versionThree->connection();
    $versionThreePdo->exec('CREATE TABLE schema_migrations (version INTEGER PRIMARY KEY, applied_at TEXT NOT NULL)');
    $versionThreePdo->exec("INSERT INTO schema_migrations VALUES (3, '2026-08-31 00:00:00')");
    $versionThreePdo->exec(
        'CREATE TABLE users (pseudo TEXT PRIMARY KEY, password_hash TEXT NOT NULL, email TEXT, '
        . 'last_name TEXT, first_name TEXT NOT NULL DEFAULT \'\', '
        . 'is_admin INTEGER NOT NULL DEFAULT 0, is_redactor INTEGER NOT NULL DEFAULT 0)'
    );
    $versionThreePdo->exec(
        "INSERT INTO users VALUES ('legacy-admin', 'hash', '', '', '', 1, 1)"
    );
    (new Migrator($versionThree))->migrate();
    assertTrue(columnExists($versionThreePdo, 'users', 'auth_version'), 'migration de la version de session');
    assertSameValue(1, (int) $versionThreePdo->query('SELECT auth_version FROM users')->fetchColumn(), 'valeur de session migrée');
    $versionThreePdo = null;
    $versionThree->close();
    unset($versionThree);

    $versionFour = new Database($temporaryRoot . '/legacy-v4/portix2.sqlite');
    $versionFourPdo = $versionFour->connection();
    $versionFourPdo->exec('CREATE TABLE schema_migrations (version INTEGER PRIMARY KEY, applied_at TEXT NOT NULL)');
    $versionFourPdo->exec("INSERT INTO schema_migrations VALUES (4, '2026-08-31 12:00:00')");
    $versionFourPdo->exec(
        'CREATE TABLE users (pseudo TEXT PRIMARY KEY, password_hash TEXT NOT NULL, email TEXT, '
        . 'last_name TEXT, first_name TEXT NOT NULL DEFAULT \'\', is_admin INTEGER NOT NULL DEFAULT 0, '
        . 'is_redactor INTEGER NOT NULL DEFAULT 0, auth_version INTEGER NOT NULL DEFAULT 1)'
    );
    $versionFourPdo->exec(
        'CREATE TABLE modules (name TEXT PRIMARY KEY, prefix TEXT NOT NULL UNIQUE, type TEXT NOT NULL, '
        . 'priority INTEGER NOT NULL DEFAULT 100, default_language TEXT NOT NULL DEFAULT \'fr\', '
        . 'enabled INTEGER NOT NULL DEFAULT 0, path TEXT NOT NULL)'
    );
    $versionFourPdo->exec(
        'CREATE TABLE menu_items (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL UNIQUE, '
        . 'description TEXT NOT NULL DEFAULT \'\', url TEXT NOT NULL, position INTEGER NOT NULL DEFAULT 0)'
    );
    $versionFourPdo->exec('CREATE TABLE agenda_items (id INTEGER PRIMARY KEY AUTOINCREMENT)');
    $versionFourPdo->exec('CREATE TABLE groups (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');
    $versionFourPdo->exec(
        'CREATE TABLE group_memberships (id INTEGER PRIMARY KEY AUTOINCREMENT, '
        . 'user_pseudo TEXT NOT NULL, group_id INTEGER NOT NULL)'
    );
    $versionFourPdo->exec("INSERT INTO modules VALUES ('Agenda', 'agd', 'module', 1, 'fr', 1, 'agenda')");
    $versionFourPdo->exec("INSERT INTO modules VALUES ('UserGroup', 'GRP', 'plugin', 1, 'fr', 1, 'group')");
    $versionFourPdo->exec("INSERT INTO menu_items (name, url) VALUES ('Agenda', 'agenda.html')");
    (new Migrator($versionFour))->migrate();
    assertSameValue(9, (int) $versionFourPdo->query('PRAGMA user_version')->fetchColumn(), 'migration depuis la version 4');
    foreach (['agenda_items', 'groups', 'group_memberships'] as $retiredTable) {
        assertSameValue(false, tableExists($versionFourPdo, $retiredTable), 'suppression de ' . $retiredTable);
    }
    assertSameValue(0, (int) $versionFourPdo->query("SELECT COUNT(*) FROM modules WHERE prefix IN ('agd', 'GRP')")->fetchColumn(), 'modules historiques désenregistrés');
    assertSameValue(0, (int) $versionFourPdo->query("SELECT COUNT(*) FROM menu_items WHERE url = 'agenda.html'")->fetchColumn(), 'entrée Agenda retirée du menu');
    $versionFourPdo = null;
    $versionFour->close();
    unset($versionFour);

    $versionFive = new Database($temporaryRoot . '/legacy-v5/portix2.sqlite');
    $versionFivePdo = $versionFive->connection();
    $versionFivePdo->exec('CREATE TABLE schema_migrations (version INTEGER PRIMARY KEY, applied_at TEXT NOT NULL)');
    $versionFivePdo->exec("INSERT INTO schema_migrations VALUES (5, '2026-09-04 00:00:00')");
    $versionFivePdo->exec(
        'CREATE TABLE users (pseudo TEXT PRIMARY KEY, password_hash TEXT NOT NULL, email TEXT, '
        . 'last_name TEXT, first_name TEXT NOT NULL DEFAULT \'\', is_admin INTEGER NOT NULL DEFAULT 0, '
        . 'is_redactor INTEGER NOT NULL DEFAULT 0, auth_version INTEGER NOT NULL DEFAULT 1)'
    );
    $versionFivePdo->exec(
        "INSERT INTO users VALUES ('preserved-admin', 'hash', 'admin@example.test', '', '', 1, 1, 3)"
    );
    (new Migrator($versionFive))->migrate();
    assertSameValue(9, (int) $versionFivePdo->query('PRAGMA user_version')->fetchColumn(), 'migration depuis la version 5');
    assertTrue(tableExists($versionFivePdo, 'rate_limits'), 'table du limiteur après migration depuis la version 5');
    assertSameValue(
        3,
        (int) $versionFivePdo->query("SELECT auth_version FROM users WHERE pseudo = 'preserved-admin'")->fetchColumn(),
        'compte préservé pendant la migration depuis la version 5'
    );
    $versionFivePdo = null;
    $versionFive->close();
    unset($versionFive);

    // Un module interne protégé par AUTH doit rester actif, tandis qu'un module
    // public ordinaire conserve le choix d'activation de l'administrateur.
    $syncDatabase = new Database($temporaryRoot . '/bundled-sync/portix2.sqlite');
    (new Migrator($syncDatabase))->migrate();
    $syncPdo = $syncDatabase->connection();
    $syncPdo->exec(
        "INSERT INTO modules (name, prefix, type, priority, default_language, enabled, path) "
        . "VALUES ('Authentification interne', 'USR', 'plugin', 1, 'fr', 0, 'UserZone')"
    );
    $syncPdo->exec(
        "INSERT INTO modules (name, prefix, type, priority, default_language, enabled, path) "
        . "VALUES ('Actualités', 'news', 'module', 10, 'fr', 0, 'news')"
    );
    (new \Portix\Module\BundledModuleSynchronizer(dirname(__DIR__), $syncDatabase))->synchronize();
    assertSameValue(
        1,
        (int) $syncPdo->query("SELECT enabled FROM modules WHERE prefix = 'USR'")->fetchColumn(),
        'module interne UserZone forcé actif'
    );
    assertSameValue(
        0,
        (int) $syncPdo->query("SELECT enabled FROM modules WHERE prefix = 'news'")->fetchColumn(),
        'état désactivé d’un module public conservé'
    );
    $syncPdo = null;
    $syncDatabase->close();
    unset($syncDatabase);

    assertSameValue('ok', $pdo->query('PRAGMA integrity_check')->fetchColumn(), 'intégrité SQLite');
    assertSameValue([], $pdo->query('PRAGMA foreign_key_check')->fetchAll(), 'clés étrangères');

    $unsupported = new Database($temporaryRoot . '/unsupported/portix2.sqlite');
    $unsupportedPdo = $unsupported->connection();
    $unsupportedPdo->exec('CREATE TABLE __portix_schema (name TEXT PRIMARY KEY)');
    $rejected = false;
    try {
        (new Migrator($unsupported))->migrate();
    } catch (RuntimeException) {
        $rejected = true;
    }
    assertTrue($rejected, 'rejet d’une base étrangère au schéma 0.7.x');
    $unsupportedPdo = null;
    $unsupported->close();
    unset($unsupported);

    echo "Schéma SQLite natif et dépôts préparés : OK\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Test SQLite natif : ÉCHEC - ' . $error->getMessage() . "\n");
    $status = 1;
} finally {
    unset($repositories, $admin, $editorBeforePassword, $editorAfterPassword, $pdo, $legacyPdo, $legacy, $versionThreePdo, $versionThree, $versionFourPdo, $versionFour, $versionFivePdo, $versionFive, $unsupportedPdo, $unsupported);
    if ($database instanceof Database) {
        $database->close();
    }
    unset($database);
    gc_collect_cycles();
    removeTestTree($temporaryRoot);
}

exit($status);

function tableExists(PDO $pdo, string $name): bool
{
    $statement = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = :name");
    $statement->execute(['name' => $name]);
    return $statement->fetchColumn() !== false;
}

function columnExists(PDO $pdo, string $table, string $column): bool
{
    foreach ($pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll() as $definition) {
        if ((string) ($definition['name'] ?? '') === $column) {
            return true;
        }
    }
    return false;
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

function removeTestTree(string $directory): void
{
    if (!is_dir($directory) || !str_starts_with(basename($directory), 'portix2-database-smoke-')) {
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
