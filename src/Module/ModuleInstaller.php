<?php
declare(strict_types=1);

namespace Portix\Module;

use PDO;
use Portix\Infrastructure\Database;
use Portix\Repository\ModuleRepository;
use RuntimeException;

final class ModuleInstaller
{
    public function __construct(
        private readonly Database $database,
        private readonly ModuleRepository $modules
    ) {
    }

    /** @return array{module:array<string, mixed>,tables:list<string>} */
    public function installDirectory(string $moduleDirectory): array
    {
        $moduleDirectory = rtrim($moduleDirectory, '/\\');
        $rootFolder = basename($moduleDirectory);
        $manifestFile = $moduleDirectory . '/desc.xml';
        if (!is_file($manifestFile)) {
            throw new RuntimeException('modules_manifest_missing');
        }
        $manifest = ModuleManifest::fromXml($manifestFile, $rootFolder);
        $schemaDirectory = $moduleDirectory . '/database';
        if (is_dir($schemaDirectory) && $manifest['schema_format'] !== 'desc-csv') {
            throw new RuntimeException('modules_schema_format_required');
        }
        if ($manifest['schema_format'] === 'desc-csv' && !is_dir($schemaDirectory)) {
            throw new RuntimeException('modules_schema_missing_directory');
        }

        return $this->database->transaction(function (PDO $unused) use ($schemaDirectory, $manifest): array {
            $tables = (new ModuleSchemaInstaller($this->database))->install(
                (string) $manifest['prefix'],
                $schemaDirectory
            );
            $this->modules->upsert($manifest);
            return ['module' => $manifest, 'tables' => $tables];
        });
    }
}
