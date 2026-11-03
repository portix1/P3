<?php
declare(strict_types=1);

namespace Portix\Module;

use FilesystemIterator;
use PDO;
use Portix\Infrastructure\Database;
use RuntimeException;

/** Installs module-owned desc.csv schemas into Portix's central SQLite database. */
final class ModuleSchemaInstaller
{
    public function __construct(private readonly Database $database)
    {
    }

    /** @return list<string> */
    public function install(string $modulePrefix, string $schemaDirectory): array
    {
        if (!is_dir($schemaDirectory)) {
            return [];
        }
        if (preg_match('/^[A-Za-z0-9_]+$/', $modulePrefix) !== 1) {
            throw new RuntimeException('modules_schema_invalid_prefix');
        }

        $descriptors = $this->discoverDescriptors($schemaDirectory);
        if ($descriptors === []) {
            return [];
        }

        return $this->database->transaction(function (PDO $pdo) use ($modulePrefix, $descriptors): array {
            $this->ensureOwnershipTable($pdo);
            $knownTables = array_fill_keys(array_keys($descriptors), true);
            $schema = [];
            foreach ($descriptors as $table => $descriptor) {
                $schema[$table] = $this->readDescriptor($descriptor, $knownTables);
            }
            $this->validateReferences($schema);
            $installed = [];

            foreach ($schema as $table => $columns) {
                $this->installTable($pdo, $modulePrefix, $table, $columns);
                $installed[] = $table;
            }

            $foreignKeyFailure = $pdo->query('PRAGMA foreign_key_check')->fetch();
            if ($foreignKeyFailure !== false) {
                throw new RuntimeException('modules_schema_foreign_key');
            }
            return $installed;
        });
    }

    /** @return array<string, string> */
    private function discoverDescriptors(string $schemaDirectory): array
    {
        $descriptors = [];
        $iterator = new FilesystemIterator($schemaDirectory, FilesystemIterator::SKIP_DOTS);
        foreach ($iterator as $entry) {
            if (!$entry->isDir() || $entry->isLink()) {
                continue;
            }
            $table = $entry->getFilename();
            if (!$this->validIdentifier($table)) {
                throw new RuntimeException('modules_schema_invalid_table');
            }
            $descriptor = $entry->getPathname() . DIRECTORY_SEPARATOR . 'desc.csv';
            if (!is_file($descriptor) || is_link($descriptor)) {
                throw new RuntimeException('modules_schema_missing_description');
            }
            $descriptors[$table] = $descriptor;
        }
        ksort($descriptors);
        return $descriptors;
    }

    /**
     * @param array<string, true> $knownTables
     * @return list<array{name:string,type:string,null:string,default:string,unique:bool,index:bool,primary:bool,reference_table:?string,reference_column:?string,on_delete:?string}>
     */
    private function readDescriptor(string $file, array $knownTables): array
    {
        $handle = fopen($file, 'rb');
        if ($handle === false) {
            throw new RuntimeException('modules_schema_read_failed');
        }

        $columns = [];
        $names = [];
        $primaryCount = 0;
        try {
            while (($row = fgetcsv($handle, 0, ';', '"', '')) !== false) {
                if (!isset($row[0]) || trim((string) $row[0]) === '') {
                    continue;
                }
                $row = array_pad($row, 9, '');
                $name = trim((string) $row[0]);
                $type = strtolower(trim((string) $row[1]));
                $nullRule = strtolower(trim((string) $row[2]));
                $uniqueRule = strtolower(trim((string) $row[4]));
                $indexRule = strtolower(trim((string) $row[5]));
                $primaryRule = strtolower(trim((string) $row[6]));
                if (!$this->validIdentifier($name) || isset($names[strtolower($name)])) {
                    throw new RuntimeException('modules_schema_invalid_column');
                }
                if (!in_array($type, ['numeric', 'bool', 'date', 'text'], true)
                    || !in_array($nullRule, ['', 'null', 'notnull'], true)
                    || !in_array($uniqueRule, ['', 'unique'], true)
                    || !in_array($indexRule, ['', 'index'], true)
                    || !in_array($primaryRule, ['', 'primary'], true)) {
                    throw new RuntimeException('modules_schema_invalid_column');
                }

                $referenceTable = null;
                $referenceColumn = null;
                $onDelete = null;
                $reference = trim((string) $row[7]);
                if ($reference !== '') {
                    if (preg_match('/^([a-z][a-z0-9_]*)\.([a-z][a-z0-9_]*)$/', $reference, $match) !== 1
                        || !isset($knownTables[$match[1]])) {
                        throw new RuntimeException('modules_schema_invalid_reference');
                    }
                    $referenceTable = $match[1];
                    $referenceColumn = $match[2];
                    $onDelete = strtolower(trim((string) $row[8]) ?: 'restrict');
                    if (!in_array($onDelete, ['restrict', 'cascade', 'setnull'], true)
                        || ($onDelete === 'setnull' && $nullRule === 'notnull')) {
                        throw new RuntimeException('modules_schema_invalid_delete_rule');
                    }
                }

                $primary = $primaryRule === 'primary';
                $primaryCount += $primary ? 1 : 0;
                $names[strtolower($name)] = true;
                $columns[] = [
                    'name' => $name,
                    'type' => $type,
                    'null' => $nullRule,
                    'default' => trim((string) $row[3]),
                    'unique' => $uniqueRule === 'unique',
                    'index' => $indexRule === 'index',
                    'primary' => $primary,
                    'reference_table' => $referenceTable,
                    'reference_column' => $referenceColumn,
                    'on_delete' => $onDelete,
                ];
            }
        } finally {
            fclose($handle);
        }

        if ($columns === [] || $primaryCount > 1) {
            throw new RuntimeException('modules_schema_invalid_primary');
        }
        return $columns;
    }

    /** @param array<string, list<array<string, mixed>>> $schema */
    private function validateReferences(array $schema): void
    {
        $targets = [];
        foreach ($schema as $table => $columns) {
            foreach ($columns as $column) {
                if (!empty($column['primary']) || !empty($column['unique'])) {
                    $targets[$table][strtolower((string) $column['name'])] = true;
                }
            }
        }
        foreach ($schema as $columns) {
            foreach ($columns as $column) {
                if ($column['reference_table'] !== null
                    && empty($targets[(string) $column['reference_table']][strtolower((string) $column['reference_column'])])) {
                    throw new RuntimeException('modules_schema_invalid_reference');
                }
            }
        }
    }

    /** @param list<array<string, mixed>> $columns */
    private function installTable(PDO $pdo, string $modulePrefix, string $table, array $columns): void
    {
        $ownership = $pdo->prepare(
            'SELECT module_prefix FROM module_schema_tables WHERE table_name = :table_name'
        );
        $ownership->execute(['table_name' => $table]);
        $owner = $ownership->fetchColumn();
        $tableExists = $this->tableExists($pdo, $table);
        if ($tableExists && $owner === false) {
            throw new RuntimeException('modules_schema_table_conflict');
        }
        if ($owner !== false && strcasecmp((string) $owner, $modulePrefix) !== 0) {
            throw new RuntimeException('modules_schema_table_owned');
        }

        if (!$tableExists) {
            $definitions = array_map(
                fn (array $column): string => $this->columnDefinition($pdo, $column, true),
                $columns
            );
            $pdo->exec(
                'CREATE TABLE ' . $this->quoteIdentifier($table)
                . ' (' . implode(', ', $definitions) . ')'
            );
        } else {
            $existing = [];
            foreach ($pdo->query('PRAGMA table_info(' . $this->quoteIdentifier($table) . ')')->fetchAll() as $row) {
                $existing[strtolower((string) $row['name'])] = $row;
            }
            foreach ($columns as $column) {
                $key = strtolower((string) $column['name']);
                if (isset($existing[$key])) {
                    if ((bool) $existing[$key]['pk'] !== (bool) $column['primary']) {
                        throw new RuntimeException('modules_schema_primary_conflict');
                    }
                    continue;
                }
                if (!empty($column['primary'])) {
                    throw new RuntimeException('modules_schema_primary_add');
                }
                $pdo->exec(
                    'ALTER TABLE ' . $this->quoteIdentifier($table) . ' ADD COLUMN '
                    . $this->columnDefinition($pdo, $column, false)
                );
            }
        }

        foreach ($columns as $column) {
            if (!empty($column['primary']) || (empty($column['unique']) && empty($column['index']))) {
                continue;
            }
            $indexName = 'idx_mod_' . substr(hash('sha256', $table . ':' . $column['name']), 0, 20);
            $pdo->exec(
                'CREATE ' . (!empty($column['unique']) ? 'UNIQUE ' : '') . 'INDEX IF NOT EXISTS '
                . $this->quoteIdentifier($indexName) . ' ON ' . $this->quoteIdentifier($table)
                . ' (' . $this->quoteIdentifier((string) $column['name']) . ')'
            );
        }

        $register = $pdo->prepare(
            'INSERT INTO module_schema_tables (module_prefix, table_name, installed_at) '
            . 'VALUES (:module_prefix, :table_name, :installed_at) '
            . 'ON CONFLICT(table_name) DO UPDATE SET installed_at = excluded.installed_at'
        );
        $register->execute([
            'module_prefix' => $modulePrefix,
            'table_name' => $table,
            'installed_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    /** @param array<string, mixed> $column */
    private function columnDefinition(PDO $pdo, array $column, bool $includePrimary): string
    {
        $sqlType = in_array($column['type'], ['numeric', 'bool'], true) ? 'INTEGER' : 'TEXT';
        $definition = $this->quoteIdentifier((string) $column['name']) . ' ' . $sqlType;
        if ($includePrimary && !empty($column['primary'])) {
            return $definition . ' PRIMARY KEY';
        }
        if ($column['null'] === 'notnull') {
            $definition .= ' NOT NULL';
        }
        if ((string) $column['default'] !== '') {
            if ($sqlType === 'INTEGER') {
                $default = strtolower((string) $column['default']);
                $booleanDefaults = ['false' => '0', 'no' => '0', 'off' => '0', 'true' => '1', 'yes' => '1', 'on' => '1'];
                $default = $booleanDefaults[$default] ?? $default;
                if (preg_match('/^-?[0-9]+$/', $default) !== 1) {
                    throw new RuntimeException('modules_schema_default_invalid');
                }
                $definition .= ' DEFAULT ' . (int) $default;
            } else {
                $definition .= ' DEFAULT ' . $pdo->quote((string) $column['default']);
            }
        }
        if ($column['type'] === 'bool') {
            $identifier = $this->quoteIdentifier((string) $column['name']);
            $definition .= ' CHECK (' . $identifier . ' IN (0, 1))';
        }
        if ($column['reference_table'] !== null) {
            $definition .= ' REFERENCES ' . $this->quoteIdentifier((string) $column['reference_table'])
                . ' (' . $this->quoteIdentifier((string) $column['reference_column']) . ') ON DELETE '
                . match ($column['on_delete']) {
                    'cascade' => 'CASCADE',
                    'setnull' => 'SET NULL',
                    default => 'RESTRICT',
                };
        }
        return $definition;
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

    private function tableExists(PDO $pdo, string $table): bool
    {
        $statement = $pdo->prepare(
            "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = :name"
        );
        $statement->execute(['name' => $table]);
        return $statement->fetchColumn() !== false;
    }

    private function validIdentifier(string $identifier): bool
    {
        return preg_match('/^[a-z][a-z0-9_]{0,62}$/', $identifier) === 1;
    }

    private function quoteIdentifier(string $identifier): string
    {
        if (!$this->validIdentifier($identifier)) {
            throw new RuntimeException('modules_schema_invalid_identifier');
        }
        return '"' . $identifier . '"';
    }
}
