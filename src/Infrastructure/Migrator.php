<?php
declare(strict_types=1);

namespace Portix\Infrastructure;

use PDO;
use Portix\Support\Slugger;
use RuntimeException;

final class Migrator
{
    private const VERSION = 8;

    public function __construct(private readonly Database $database)
    {
    }

    public function migrate(): void
    {
        $pdo = $this->database->connection();
        if (!$this->tableExists($pdo, 'schema_migrations')) {
            if ($this->hasApplicationTables($pdo)) {
                throw new RuntimeException(
                    'Cette base précède le socle SQLite natif de P3 0.7.x et n’est plus prise en charge.'
                );
            }
            $this->database->transaction(function (PDO $pdo): void {
                $this->createMigrationTable($pdo);
                $this->createCurrentSchema($pdo);
                $this->recordVersion($pdo, self::VERSION);
            });
            return;
        }

        $current = (int) $pdo->query('SELECT COALESCE(MAX(version), 0) FROM schema_migrations')->fetchColumn();
        if ($current === 2) {
            $this->database->transaction(function (PDO $pdo): void {
                $this->migrateDownloadsToModuleSchema($pdo);
                $this->recordVersion($pdo, 3);
            });
            $current = 3;
        }
        if ($current === 3) {
            $this->database->transaction(function (PDO $pdo): void {
                $this->addUserAuthenticationVersion($pdo);
                $this->recordVersion($pdo, 4);
            });
            $current = 4;
        }
        if ($current === 4) {
            $this->database->transaction(function (PDO $pdo): void {
                $this->removeRetiredAgendaAndGroups($pdo);
                $this->recordVersion($pdo, 5);
            });
            $current = 5;
        }
        if ($current === 5) {
            $this->database->transaction(function (PDO $pdo): void {
                $this->createRateLimitSchema($pdo);
                $this->recordVersion($pdo, 6);
            });
            $current = 6;
        }
        if ($current === 6) {
            $this->database->transaction(function (PDO $pdo): void {
                $this->createContentSafetySchema($pdo);
                $this->recordVersion($pdo, 7);
            });
            $current = 7;
        }
        if ($current === 7) {
            $this->database->transaction(function (PDO $pdo): void {
                if ($this->tableExists($pdo, 'users')) {
                    $pdo->exec("ALTER TABLE users ADD COLUMN article_permissions TEXT NOT NULL DEFAULT '{}'");
                }
                $this->recordVersion($pdo, self::VERSION);
            });
            $current = self::VERSION;
        }
        if ($current !== self::VERSION) {
            throw new RuntimeException('Version de schéma SQLite non prise en charge : ' . $current . '.');
        }
        $this->createCurrentSchema($pdo);
    }

    private function createCurrentSchema(PDO $pdo): void
    {
        $statements = [
            'CREATE TABLE IF NOT EXISTS users ('
                . 'pseudo TEXT PRIMARY KEY, password_hash TEXT NOT NULL, email TEXT, '
                . 'last_name TEXT, first_name TEXT NOT NULL DEFAULT \'\', '
                . 'is_admin INTEGER NOT NULL DEFAULT 0 CHECK (is_admin IN (0, 1)), '
                . 'is_redactor INTEGER NOT NULL DEFAULT 0 CHECK (is_redactor IN (0, 1)), '
                . 'auth_version INTEGER NOT NULL DEFAULT 1, '
                . "article_permissions TEXT NOT NULL DEFAULT '{}', "
                . 'CHECK (is_admin = 1 OR is_redactor = 1))',
            'CREATE INDEX IF NOT EXISTS idx_users_email ON users (email)',
            'CREATE INDEX IF NOT EXISTS idx_users_last_name ON users (last_name)',

            'CREATE TABLE IF NOT EXISTS modules ('
                . 'name TEXT PRIMARY KEY, prefix TEXT NOT NULL UNIQUE, '
                . 'type TEXT NOT NULL CHECK (type IN (\'module\', \'plugin\')), '
                . 'priority INTEGER NOT NULL DEFAULT 100, default_language TEXT NOT NULL DEFAULT \'fr\', '
                . 'enabled INTEGER NOT NULL DEFAULT 0 CHECK (enabled IN (0, 1)), path TEXT NOT NULL)',
            'CREATE INDEX IF NOT EXISTS idx_modules_order ON modules (priority, name)',

            'CREATE TABLE IF NOT EXISTS menu_items ('
                . 'id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL UNIQUE, '
                . 'description TEXT NOT NULL DEFAULT \'\', url TEXT NOT NULL, '
                . 'position INTEGER NOT NULL DEFAULT 0)',
            'CREATE INDEX IF NOT EXISTS idx_menu_items_position ON menu_items (position, id)',

            'CREATE TABLE IF NOT EXISTS news ('
                . 'id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL, slug TEXT NOT NULL UNIQUE, '
                . 'created_by TEXT NOT NULL, intro TEXT NOT NULL DEFAULT \'\', body TEXT NOT NULL DEFAULT \'\', '
                . 'status TEXT NOT NULL DEFAULT \'draft\' CHECK (status IN (\'draft\', \'published\')), '
                . 'published_at TEXT, created_at TEXT NOT NULL, updated_at TEXT NOT NULL)',
            'CREATE INDEX IF NOT EXISTS idx_news_publication ON news (status, published_at DESC, id DESC)',
            'CREATE INDEX IF NOT EXISTS idx_news_updated ON news (updated_at DESC, id DESC)',

        ];

        foreach ($statements as $statement) {
            $pdo->exec($statement);
        }
        $this->addUserAuthenticationVersion($pdo);
        $this->createRateLimitSchema($pdo);
        $this->createContentSafetySchema($pdo);
    }


    private function createContentSafetySchema(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS content_revisions ('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT, content_type TEXT NOT NULL, content_id INTEGER NOT NULL, '
            . "title TEXT NOT NULL DEFAULT '', snapshot_json TEXT NOT NULL, created_by TEXT NOT NULL DEFAULT '', "
            . 'created_at TEXT NOT NULL)'
        );
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS idx_content_revisions_target '
            . 'ON content_revisions (content_type, content_id, id DESC)'
        );
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS content_trash ('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT, content_type TEXT NOT NULL, original_id INTEGER NOT NULL, '
            . "title TEXT NOT NULL DEFAULT '', snapshot_json TEXT NOT NULL, deleted_by TEXT NOT NULL DEFAULT '', "
            . 'deleted_at TEXT NOT NULL)'
        );
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS idx_content_trash_deleted ON content_trash (deleted_at, id)'
        );
    }

    private function createRateLimitSchema(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS rate_limits ('
            . 'rate_key TEXT PRIMARY KEY, failures INTEGER NOT NULL DEFAULT 0 CHECK (failures >= 0), '
            . 'blocked_until INTEGER NOT NULL DEFAULT 0, updated_at INTEGER NOT NULL)'
        );
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS idx_rate_limits_updated ON rate_limits (updated_at)'
        );
    }

    private function createMigrationTable(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE schema_migrations (version INTEGER PRIMARY KEY, applied_at TEXT NOT NULL)'
        );
    }

    private function recordVersion(PDO $pdo, int $version): void
    {
        $statement = $pdo->prepare(
            'INSERT INTO schema_migrations (version, applied_at) VALUES (:version, :applied_at)'
        );
        $statement->execute(['version' => $version, 'applied_at' => gmdate('Y-m-d H:i:s')]);
        $pdo->exec('PRAGMA user_version = ' . $version);
    }

    private function migrateDownloadsToModuleSchema(PDO $pdo): void
    {
        $this->ensureOwnershipTable($pdo);
        if (!$this->tableExists($pdo, 'download_categories') || !$this->tableExists($pdo, 'downloads')) {
            return;
        }

        $pdo->exec(
            'CREATE TABLE download_categories_portix3 ('
            . 'id INTEGER PRIMARY KEY, name TEXT NOT NULL, slug TEXT NOT NULL UNIQUE, '
            . 'description TEXT NOT NULL DEFAULT \'\', position INTEGER NOT NULL DEFAULT 0)'
        );
        $categoryInsert = $pdo->prepare(
            'INSERT INTO download_categories_portix3 (id, name, slug, description, position) '
            . 'VALUES (:id, :name, :slug, :description, :position)'
        );
        $usedSlugs = [];
        foreach ($pdo->query('SELECT id, name, description FROM download_categories ORDER BY id')->fetchAll() as $row) {
            $base = $this->slug((string) $row['name']);
            $slug = $base;
            $suffix = 2;
            while (isset($usedSlugs[$slug])) {
                $slug = $base . '-' . $suffix++;
            }
            $usedSlugs[$slug] = true;
            $categoryInsert->execute([
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'slug' => $slug,
                'description' => (string) ($row['description'] ?? ''),
                'position' => (int) $row['id'],
            ]);
        }

        $pdo->exec(
            'CREATE TABLE downloads_portix3 ('
            . 'id INTEGER PRIMARY KEY, category_id INTEGER, title TEXT NOT NULL, '
            . 'original_name TEXT NOT NULL DEFAULT \'\', stored_name TEXT NOT NULL, '
            . 'mime_type TEXT NOT NULL DEFAULT \'application/octet-stream\', '
            . 'size_bytes INTEGER NOT NULL DEFAULT 0, sha256 TEXT NOT NULL DEFAULT \'\', '
            . 'description TEXT NOT NULL DEFAULT \'\', '
            . 'status TEXT NOT NULL DEFAULT \'published\', position INTEGER NOT NULL DEFAULT 0, '
            . 'download_count INTEGER NOT NULL DEFAULT 0, created_at TEXT NOT NULL, updated_at TEXT NOT NULL, '
            . 'FOREIGN KEY (category_id) REFERENCES download_categories_portix3(id) ON DELETE SET NULL)'
        );
        $now = gmdate('Y-m-d H:i:s');
        $downloadInsert = $pdo->prepare(
            'INSERT INTO downloads_portix3 '
            . '(id, category_id, title, original_name, stored_name, mime_type, size_bytes, sha256, '
            . 'description, status, position, download_count, created_at, updated_at) '
            . 'VALUES (:id, :category_id, :title, :original_name, :stored_name, :mime_type, 0, \'\', '
            . ':description, \'published\', :position, :download_count, :created_at, :updated_at)'
        );
        foreach ($pdo->query('SELECT * FROM downloads ORDER BY id')->fetchAll() as $row) {
            $downloadInsert->execute([
                'id' => (int) $row['id'],
                'category_id' => $row['category_id'] === null ? null : (int) $row['category_id'],
                'title' => (string) $row['title'],
                'original_name' => (string) $row['title'],
                'stored_name' => (string) $row['stored_name'],
                'mime_type' => 'application/octet-stream',
                'description' => (string) ($row['description'] ?? ''),
                'position' => (int) $row['id'],
                'download_count' => max(0, (int) ($row['download_count'] ?? 0)),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $pdo->exec('DROP TABLE downloads');
        $pdo->exec('DROP TABLE download_categories');
        $pdo->exec('ALTER TABLE download_categories_portix3 RENAME TO download_categories');
        $pdo->exec('ALTER TABLE downloads_portix3 RENAME TO downloads');
        $pdo->exec('CREATE INDEX idx_download_categories_position ON download_categories (position, id)');
        $pdo->exec('CREATE INDEX idx_downloads_category ON downloads (category_id, status, position, id)');
        $this->registerOwnership($pdo, 'dwl', 'download_categories');
        $this->registerOwnership($pdo, 'dwl', 'downloads');
    }

    private function addUserAuthenticationVersion(PDO $pdo): void
    {
        if (!$this->tableExists($pdo, 'users')) {
            return;
        }
        foreach ($pdo->query('PRAGMA table_info(users)')->fetchAll() as $column) {
            if ((string) ($column['name'] ?? '') === 'auth_version') {
                return;
            }
        }
        $pdo->exec('ALTER TABLE users ADD COLUMN auth_version INTEGER NOT NULL DEFAULT 1');
    }

    private function removeRetiredAgendaAndGroups(PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS group_memberships');
        $pdo->exec('DROP TABLE IF EXISTS groups');
        $pdo->exec('DROP TABLE IF EXISTS agenda_items');
        if ($this->tableExists($pdo, 'modules')) {
            $pdo->exec(
                "DELETE FROM modules WHERE lower(prefix) IN ('agd', 'grp') "
                . "OR lower(path) IN ('agenda', 'group')"
            );
        }
        if ($this->tableExists($pdo, 'menu_items')) {
            $pdo->exec(
                "DELETE FROM menu_items WHERE lower(trim(url)) "
                . "IN ('agd', 'agd.html', 'agenda', 'agenda.html')"
            );
        }
    }

    private function ensureOwnershipTable(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS module_schema_tables ('
            . 'module_prefix TEXT NOT NULL, table_name TEXT PRIMARY KEY, installed_at TEXT NOT NULL)'
        );
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS idx_module_schema_owner '
            . 'ON module_schema_tables (module_prefix, table_name)'
        );
    }

    private function registerOwnership(PDO $pdo, string $prefix, string $table): void
    {
        $statement = $pdo->prepare(
            'INSERT INTO module_schema_tables (module_prefix, table_name, installed_at) '
            . 'VALUES (:prefix, :table_name, :installed_at) '
            . 'ON CONFLICT(table_name) DO UPDATE SET module_prefix = excluded.module_prefix, '
            . 'installed_at = excluded.installed_at'
        );
        $statement->execute([
            'prefix' => $prefix,
            'table_name' => $table,
            'installed_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    private function slug(string $value): string
    {
        return Slugger::fromText($value, 'categorie');
    }

    private function hasApplicationTables(PDO $pdo): bool
    {
        return (int) $pdo->query(
            "SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'"
        )->fetchColumn() > 0;
    }

    private function tableExists(PDO $pdo, string $table): bool
    {
        $statement = $pdo->prepare(
            'SELECT 1 FROM sqlite_master WHERE type = \'table\' AND name = :name'
        );
        $statement->execute(['name' => $table]);
        return $statement->fetchColumn() !== false;
    }
}
