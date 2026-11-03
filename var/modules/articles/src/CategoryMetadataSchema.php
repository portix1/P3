<?php
declare(strict_types=1);

namespace PortixModule\Articles;

use Portix\Infrastructure\Database;
use Portix\Module\ModuleSchemaInstaller;
use RuntimeException;

/** Upgrades an existing Articles module when its administration is first opened. */
final class CategoryMetadataSchema
{
    public static function ensure(Database $database, string $root): void
    {
        $pdo = $database->connection();
        $columns = $pdo->query('PRAGMA table_info(article_categories)')->fetchAll();
        if ($columns === [] || in_array('description', array_column($columns, 'name'), true)) {
            return;
        }

        // VACUUM INTO includes WAL data; copying the main SQLite file would not.
        if ($database->file() !== ':memory:') {
            $directory = rtrim($root, '/\\') . '/var/private/category-description-backups';
            if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
                throw new RuntimeException('articles_category_backup_failed');
            }
            $backup = $directory . '/before-category-description-'
                . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(6)) . '.sqlite';
            $pdo->exec('VACUUM INTO ' . $pdo->quote($backup));
        }

        (new ModuleSchemaInstaller($database))->install(
            'articles',
            rtrim($root, '/\\') . '/var/modules/articles/database'
        );
    }
}
