<?php
declare(strict_types=1);

namespace Portix\Module;

use FilesystemIterator;
use Portix\Infrastructure\Database;
use Portix\Repository\ModuleRepository;

final class BundledModuleSynchronizer
{
    private const RETIRED_PREFIXES = ['NSC', 'COUNT', 'log', 'meta', 'agd', 'GRP'];

    public function __construct(
        private readonly string $root,
        private readonly Database $database
    ) {
    }

    public function synchronize(): void
    {
        $modules = new ModuleRepository($this->database->connection());
        foreach (self::RETIRED_PREFIXES as $prefix) {
            $modules->deleteByPrefix($prefix);
        }

        $directory = $this->root . '/var/modules';
        if (!is_dir($directory)) {
            return;
        }

        $entries = [];
        foreach (new FilesystemIterator($directory, FilesystemIterator::SKIP_DOTS) as $entry) {
            if ($entry->isDir() && !$entry->isLink() && is_file($entry->getPathname() . '/desc.xml')) {
                $entries[] = $entry->getPathname();
            }
        }
        sort($entries);

        foreach ($entries as $moduleDirectory) {
            $path = basename($moduleDirectory);
            $manifest = ModuleManifest::fromXml($moduleDirectory . '/desc.xml', $path);
            if ($manifest['schema_format'] === 'desc-csv') {
                (new ModuleSchemaInstaller($this->database))->install(
                    (string) $manifest['prefix'],
                    $moduleDirectory . '/database'
                );
            }
            $modules->synchronizeDefinition($manifest);
            // Les modules internes protégés par AUTH font partie du noyau fonctionnel.
            // Leur désactivation casserait notamment les routes login/personnal/disconnect.
            if (is_file($moduleDirectory . '/AUTH')) {
                $modules->setEnabled((string) $manifest['prefix'], true);
            }
        }
    }
}
