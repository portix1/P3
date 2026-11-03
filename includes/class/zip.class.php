<?php
/** Safe ZIP operations backed by PHP's maintained ZipArchive extension. */
final class PortixZip
{
    public static function requireExtension()
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('L’extension PHP zip est requise.');
        }
    }

    public static function extractSingleRoot($archiveFile, $destination)
    {
        self::requireExtension();
        $zip = new ZipArchive();
        if ($zip->open($archiveFile) !== true || $zip->numFiles < 1) {
            throw new RuntimeException('Archive ZIP vide ou illisible.');
        }
        $root = null;
        try {
            $expandedSize = 0;
            if ($zip->numFiles > 1000) {
                throw new RuntimeException('L’archive contient trop de fichiers.');
            }
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $name = str_replace('\\', '/', (string) $zip->getNameIndex($index));
                $parts = explode('/', trim($name, '/'));
                if ($name === '' || $name[0] === '/' || strpos($name, "\0") !== false
                    || in_array('..', $parts, true) || preg_match('/^[A-Za-z]:/', $name)) {
                    throw new RuntimeException('Chemin dangereux dans l’archive.');
                }
                $stat = $zip->statIndex($index);
                $expandedSize += is_array($stat) ? (int) ($stat['size'] ?? 0) : 0;
                if ($expandedSize > 25 * 1024 * 1024) {
                    throw new RuntimeException('Le contenu décompressé dépasse 25 Mio.');
                }
                $operations = 0;
                $attributes = 0;
                if ($zip->getExternalAttributesIndex($index, $operations, $attributes)
                    && (($attributes >> 16) & 0170000) === 0120000) {
                    throw new RuntimeException('Les liens symboliques sont interdits dans les modules.');
                }
                if ($root === null) {
                    $root = $parts[0];
                } elseif ($parts[0] !== $root) {
                    throw new RuntimeException('L’archive doit contenir un seul dossier racine.');
                }
            }
            if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_.-]{0,62}$/', (string) $root)) {
                throw new RuntimeException('Nom de dossier racine invalide.');
            }
            if (!$zip->extractTo($destination)) {
                throw new RuntimeException('Impossible d’extraire l’archive.');
            }
        } finally {
            $zip->close();
        }
        return $root;
    }

    public static function createFromDirectory($archiveFile, $sourceDirectory, $prefix = '')
    {
        self::requireExtension();
        $zip = new ZipArchive();
        if ($zip->open($archiveFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Impossible de créer l’archive ZIP.');
        }
        try {
            $rootLength = strlen(rtrim($sourceDirectory, '/\\')) + 1;
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($sourceDirectory, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY
            );
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $localName = trim(str_replace('\\', '/', (string) $prefix), '/') . '/'
                        . str_replace('\\', '/', substr($file->getPathname(), $rootLength));
                    $localName = ltrim($localName, '/');
                    if (!$zip->addFile($file->getPathname(), $localName)) {
                        throw new RuntimeException('Impossible d’ajouter ' . $localName . ' à l’archive.');
                    }
                }
            }
        } finally {
            $zip->close();
        }
    }

    public static function isReadableArchive($archiveFile)
    {
        if (!class_exists('ZipArchive') || !is_file($archiveFile)) {
            return false;
        }
        $zip = new ZipArchive();
        $result = $zip->open($archiveFile, ZipArchive::CHECKCONS);
        if ($result !== true) {
            return false;
        }
        $readable = $zip->numFiles > 0;
        $zip->close();
        return $readable;
    }
}
