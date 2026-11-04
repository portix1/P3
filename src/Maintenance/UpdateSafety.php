<?php
declare(strict_types=1);

namespace Portix\Maintenance;

use PDO;
use Portix\Configuration\SiteConfiguration;
use RuntimeException;
use Throwable;

final class UpdateSafety
{
    private const RELEASE_STATE = 'var/sys/release.json';
    private const BACKUP_DIRECTORY = 'var/private/update-backups';
    private const MAINTENANCE_LOCK = 'var/sys/maintenance.lock';

    public static function assertInstalled(string $root): void
    {
        $root = rtrim($root, '/\\');
        foreach (['var/sys/config.json', SiteConfiguration::load($root)->databasePath()] as $relative) {
            $path = $root . '/' . $relative;
            if (!is_file($path)) {
                throw new RuntimeException(
                    'Mise à jour refusée : installation existante incomplète (' . $relative . ' absent).'
                );
            }
            if (is_link($path)) {
                throw new RuntimeException(
                    'Mise à jour refusée : lien symbolique inattendu (' . $relative . ').'
                );
            }
        }
    }

    public static function installedRelease(string $root): ?string
    {
        $file = rtrim($root, '/\\') . '/' . self::RELEASE_STATE;
        if (!is_file($file)) {
            return null;
        }
        $json = file_get_contents($file);
        if ($json === false) {
            throw new RuntimeException('Impossible de lire la version installée de P3.');
        }
        $data = json_decode($json, true);
        if (!is_array($data) || !isset($data['version']) || !is_string($data['version'])) {
            throw new RuntimeException('Le fichier de suivi de version P3 est invalide.');
        }
        $version = trim($data['version']);
        return $version === '' ? null : $version;
    }

    public static function markRelease(string $root, string $version, string $operation): void
    {
        $root = rtrim($root, '/\\');
        $file = $root . '/' . self::RELEASE_STATE;
        $directory = dirname($file);
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new RuntimeException('Impossible de préparer le suivi de version P3.');
        }
        $payload = json_encode([
            'version' => $version,
            'operation' => $operation,
            'recorded_at' => gmdate('c'),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($payload)) {
            throw new RuntimeException('Impossible de sérialiser le suivi de version P3.');
        }
        $temporary = $file . '.tmp-' . bin2hex(random_bytes(6));
        if (file_put_contents($temporary, $payload . "\n", LOCK_EX) === false) {
            throw new RuntimeException('Impossible d’écrire le suivi de version P3.');
        }
        $previous = null;
        if (DIRECTORY_SEPARATOR === '\\' && is_file($file)) {
            $previous = $file . '.bak-' . bin2hex(random_bytes(6));
            if (!rename($file, $previous)) {
                @unlink($temporary);
                throw new RuntimeException('Impossible de préparer le remplacement du suivi de version P3.');
            }
        }
        if (!rename($temporary, $file)) {
            if ($previous !== null) {
                @rename($previous, $file);
            }
            @unlink($temporary);
            throw new RuntimeException('Impossible d’activer le suivi de version P3.');
        }
        if ($previous !== null) {
            @unlink($previous);
        }
    }

    private static function versionFile(string $file): ?string
    {
        if (!is_file($file)) { return null; }
        if (is_link($file)) { throw new RuntimeException('Lien symbolique refusé pour VERSION.'); }
        $bytes = file_get_contents($file);
        if ($bytes === false) { throw new RuntimeException('Impossible de lire VERSION.'); }
        $version = trim($bytes);
        return $version === '' ? null : $version;
    }

    private static function rollbackVersion(string $source, array $manifest): string
    {
        // A manual update may leave release.json behind; use the code actually saved.
        return self::versionFile($source . '/application/VERSION')
            ?? (string) ($manifest['previous_version'] ?? '');
    }

    private static function rollbackParent(string $root, bool $create = false): string
    {
        $root = rtrim($root, '/\\');
        foreach (['var', 'var/private', self::BACKUP_DIRECTORY] as $relative) {
            if (is_link($root . '/' . $relative)) {
                throw new RuntimeException('Lien symbolique refusé dans le dossier des points de restauration.');
            }
        }
        $parent = $root . '/' . self::BACKUP_DIRECTORY;
        if ($create && !is_dir($parent) && !mkdir($parent, 0700, true) && !is_dir($parent)) {
            throw new RuntimeException('Impossible de créer le dossier des points de restauration.');
        }
        return $parent;
    }

    private static function withRollbackLock(string $root, callable $operation): mixed
    {
        $file = self::rollbackParent($root, true) . '/.operations.lock';
        if (is_link($file)) { throw new RuntimeException('Lien symbolique refusé pour le verrou de restauration.'); }
        $lock = fopen($file, 'c');
        if ($lock === false) { throw new RuntimeException('Impossible de verrouiller les points de restauration.'); }
        try {
            if (!flock($lock, LOCK_EX | LOCK_NB)) {
                throw new RuntimeException('Une opération sur les points de restauration est déjà en cours. Réessayez après sa fin.');
            }
            return $operation();
        } finally { flock($lock, LOCK_UN); fclose($lock); }
    }

    private static function rollbackSource(string $root, string $name): string
    {
        if (preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]*\z/', $name) !== 1) {
            throw new RuntimeException('Nom de point de restauration invalide.');
        }
        $source = self::rollbackParent($root) . '/' . $name;
        if (is_link($source) || is_link($source . '/manifest.json') || is_link($source . '/application')) {
            throw new RuntimeException('Lien symbolique refusé dans le point de restauration.');
        }
        return $source;
    }

    public static function enableMaintenance(string $root, string $targetVersion): void
    {
        $root = rtrim($root, '/\\');
        $file = $root . '/' . self::MAINTENANCE_LOCK;
        $directory = dirname($file);
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new RuntimeException('Impossible d’activer le mode maintenance.');
        }
        $payload = json_encode([
            'started_at' => gmdate('c'),
            'target_version' => $targetVersion,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($payload) || file_put_contents($file, $payload . "\n", LOCK_EX) === false) {
            throw new RuntimeException('Impossible d’activer le mode maintenance.');
        }
    }

    public static function disableMaintenance(string $root): void
    {
        $file = rtrim($root, '/\\') . '/' . self::MAINTENANCE_LOCK;
        if (is_file($file) && !unlink($file)) {
            throw new RuntimeException('Impossible de désactiver le mode maintenance.');
        }
    }

    public static function createBackup(string $root, string $targetVersion): string
    {
        self::assertInstalled($root);
        $root = rtrim($root, '/\\');
        $backupParent = $root . '/' . self::BACKUP_DIRECTORY;
        if (!is_dir($backupParent) && !mkdir($backupParent, 0700, true) && !is_dir($backupParent)) {
            throw new RuntimeException('Impossible de créer le dossier des sauvegardes de mise à jour.');
        }
        if (is_link($backupParent)) {
            throw new RuntimeException('Le dossier des sauvegardes ne peut pas être un lien symbolique.');
        }

        $estimated = self::estimateBackupBytes($root);
        $free = @disk_free_space($backupParent);
        if (is_float($free) && $free > 0 && $free < ($estimated + 20 * 1024 * 1024)) {
            throw new RuntimeException('Espace disque insuffisant pour créer la sauvegarde avant mise à jour.');
        }

        $safeVersion = preg_replace('/[^A-Za-z0-9._-]+/', '-', $targetVersion) ?: 'unknown';
        $baseName = gmdate('Ymd-His') . '-to-' . $safeVersion;
        [$partial, $final] = self::uniqueBackupPaths($backupParent, $baseName);
        if (!mkdir($partial, 0700, true) && !is_dir($partial)) {
            throw new RuntimeException('Impossible de préparer la sauvegarde de mise à jour.');
        }

        try {
            self::snapshotDatabase(
                $root . '/' . SiteConfiguration::load($root)->databasePath(),
                $partial . '/database/portix2.sqlite'
            );
            self::copyTree($root . '/var/sys', $partial . '/var/sys', ['maintenance.lock']);
            self::copyTree($root . '/var/uploads/images', $partial . '/var/uploads/images');
            self::copyTree(
                $root . '/var/private',
                $partial . '/var/private',
                ['update-backups']
            );
            if (is_dir($root . '/download')) {
                self::copyTree($root . '/download', $partial . '/download');
            }

            $manifest = json_encode([
                'created_at' => gmdate('c'),
                'target_version' => $targetVersion,
                'previous_version' => self::versionFile($root . '/VERSION') ?? self::installedRelease($root),
                'contents' => [
                    'database/portix2.sqlite',
                    'var/sys/',
                    'var/uploads/images/',
                    'var/private/ (hors update-backups)',
                    'download/ si présent',
                ],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (!is_string($manifest)
                || file_put_contents($partial . '/manifest.json', $manifest . "\n", LOCK_EX) === false) {
                throw new RuntimeException('Impossible d’écrire le manifeste de sauvegarde.');
            }
            if (!rename($partial, $final)) {
                throw new RuntimeException('Impossible de finaliser la sauvegarde de mise à jour.');
            }
        } catch (Throwable $error) {
            throw new RuntimeException(
                'Sauvegarde préalable incomplète dans ' . self::relativePath($root, $partial)
                . ' : ' . $error->getMessage(),
                0,
                $error
            );
        }

        return $final;
    }

    /** @return list<array{name:string,version:string,date:string}> */
    public static function listRollbackPoints(string $root): array
    {
        $parent=self::rollbackParent($root);
        if (!is_dir($parent)) return [];
        $rows=[];
        foreach (scandir($parent) ?: [] as $name) {
            if (preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]*\z/', $name)!==1 || is_link($parent.'/'.$name)) continue;
            $source=self::rollbackSource($root,$name);
            $manifest=$source.'/manifest.json'; if(!is_file($manifest)) continue;
            $data=json_decode((string)file_get_contents($manifest),true);
            if(!is_array($data)||($data['kind']??'')!=='rollback_point') continue;
            $rows[]=['name'=>$name,'version'=>self::rollbackVersion($source,$data),'date'=>(string)($data['created_at']??'')];
        }
        usort($rows,static fn(array $a,array $b):int=>strcmp($b['date'],$a['date'])); return $rows;
    }

    public static function createRollbackPoint(string $root): string
    {
        return self::withRollbackLock($root, static fn(): string => self::createRollbackPointUnlocked($root));
    }

    private static function createRollbackPointUnlocked(string $root): string
    {
        self::assertInstalled($root); $root=rtrim($root,'/\\');
        $parent=self::rollbackParent($root,true);
        [$partial,$final]=self::uniqueBackupPaths($parent,gmdate('Ymd-His').'-rollback');
        if(!mkdir($partial,0700,true)&&!is_dir($partial)) throw new RuntimeException('Impossible de préparer le point de restauration.');
        try {
            self::snapshotDatabase($root.'/'.SiteConfiguration::load($root)->databasePath(),$partial.'/database/portix2.sqlite');
            self::copyTree($root.'/var/sys',$partial.'/var/sys',['maintenance.lock']);
            self::copyTree($root.'/var/uploads/images',$partial.'/var/uploads/images');
            self::copyTree($root.'/var/private',$partial.'/var/private',['update-backups']);
            if(is_dir($root.'/download')) self::copyTree($root.'/download',$partial.'/download');
            $app=$partial.'/application'; if(!mkdir($app,0700,true)&&!is_dir($app)) throw new RuntimeException('Impossible de préparer la copie applicative.');
            foreach(['bootstrap','includes','src','tools'] as $dir) self::copyTree($root.'/'.$dir,$app.'/'.$dir);
            foreach(['admin','editor','lang','modules','templates'] as $dir) self::copyTree($root.'/var/'.$dir,$app.'/var/'.$dir);
            $rootFiles=[];
            foreach(scandir($root)?:[] as $entry){ $from=$root.'/'.$entry; if($entry==='.'||$entry==='..'||!is_file($from)||is_link($from)) continue; if(!copy($from,$app.'/'.$entry)) throw new RuntimeException('Impossible de sauvegarder '.$entry); $rootFiles[]=$entry; }
            $manifest=['kind'=>'rollback_point','created_at'=>gmdate('c'),'previous_version'=>self::versionFile($app.'/VERSION') ?? self::installedRelease($partial),'root_files'=>$rootFiles];
            if(file_put_contents($partial.'/manifest.json',json_encode($manifest,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n",LOCK_EX)===false) throw new RuntimeException('Impossible d’écrire le manifeste de rollback.');
            if(!rename($partial,$final)) throw new RuntimeException('Impossible de finaliser le point de restauration.');
        } catch(Throwable $e){ throw new RuntimeException('Point de restauration incomplet : '.$e->getMessage(),0,$e); }
        return $final;
    }

    public static function restoreRollbackPoint(string $root,string $name): void
    {
        self::withRollbackLock($root, static function () use ($root, $name): void {
            self::restoreRollbackPointUnlocked($root, $name);
        });
    }

    private static function restoreRollbackPointUnlocked(string $root,string $name): void
    {
        $root=rtrim($root,'/\\');
        $source=self::rollbackSource($root,$name); $manifestFile=$source.'/manifest.json';
        if(!is_file($manifestFile)) throw new RuntimeException('Point de restauration introuvable.');
        $manifest=json_decode((string)file_get_contents($manifestFile),true); if(!is_array($manifest)||($manifest['kind']??'')!=='rollback_point') throw new RuntimeException('Ce dossier n’est pas un point de restauration complet.');
        $activeConfig = SiteConfiguration::load($root);
        $backupConfig = SiteConfiguration::load($source);
        if (!$backupConfig->isInstalled() || $backupConfig->databasePath() !== $activeConfig->databasePath()) {
            throw new RuntimeException('Ce point de restauration correspond à un autre fichier SQLite.');
        }
        $version = self::rollbackVersion($source, $manifest);
        self::createRollbackPointUnlocked($root); // point de secours avant restauration, sous le même verrou
        self::enableMaintenance($root, $version !== '' ? $version : 'rollback');
        try {
            foreach(['bootstrap','includes','src','tools'] as $dir){ self::removeTree($root.'/'.$dir); self::copyTree($source.'/application/'.$dir,$root.'/'.$dir); }
            foreach(['admin','editor','lang','modules','templates'] as $dir){ self::removeTree($root.'/var/'.$dir); self::copyTree($source.'/application/var/'.$dir,$root.'/var/'.$dir); }
            foreach(($manifest['root_files']??[]) as $entry){ if(!is_string($entry)||basename($entry)!==$entry) continue; if(is_file($source.'/application/'.$entry)&&!copy($source.'/application/'.$entry,$root.'/'.$entry)) throw new RuntimeException('Impossible de restaurer '.$entry); }
            if(!copy($source.'/database/portix2.sqlite',$root.'/'.SiteConfiguration::load($root)->databasePath())) throw new RuntimeException('Impossible de restaurer la base SQLite.');
            self::replaceTree($source.'/var/sys',$root.'/var/sys',['maintenance.lock']);
            self::replaceTree($source.'/var/uploads/images',$root.'/var/uploads/images');
            self::replaceTree($source.'/var/private',$root.'/var/private',['update-backups']);
            if(is_dir($source.'/download')) self::replaceTree($source.'/download',$root.'/download');
            if ($version !== '') { self::markRelease($root, $version, 'restore'); }
        } finally { self::disableMaintenance($root); }
    }

    public static function deleteRollbackPoint(string $root, string $name): void
    {
        self::withRollbackLock($root, static function () use ($root, $name): void {
            $source = self::rollbackSource($root, $name);
            $file = $source . '/manifest.json';
            if (!is_file($file)) { throw new RuntimeException('Point de restauration introuvable.'); }
            $manifest = json_decode((string) file_get_contents($file), true);
            if (!is_array($manifest) || ($manifest['kind'] ?? '') !== 'rollback_point') {
                throw new RuntimeException('Ce dossier n’est pas un point de restauration complet.');
            }
            // Reject a linked descendant before removing any file from the selected point.
            self::assertDeletableTree($source);
            self::removeTree($source);
        });
    }

    private static function assertDeletableTree(string $path): void
    {
        if (is_link($path)) { throw new RuntimeException('Lien symbolique refusé pendant la suppression du point.'); }
        if (is_file($path)) { return; }
        if (!is_dir($path)) { throw new RuntimeException('Fichier inattendu dans le point de restauration.'); }
        $entries = scandir($path);
        if ($entries === false || !is_writable($path)) {
            throw new RuntimeException('Le dossier du point de restauration ne peut pas être supprimé.');
        }
        foreach ($entries as $entry) {
            if ($entry !== '.' && $entry !== '..') { self::assertDeletableTree($path . '/' . $entry); }
        }
    }

    /** @param list<string> $preserve */
    private static function replaceTree(string $source,string $destination,array $preserve=[]): void
    {
        if(!is_dir($source)) return;
        if(is_dir($destination)) {
            foreach(scandir($destination)?:[] as $entry){ if($entry==='.'||$entry==='..'||in_array($entry,$preserve,true)) continue; self::removeTree($destination.'/'.$entry); }
        }
        self::copyTree($source,$destination);
    }

    private static function removeTree(string $path): void
    {
        if(is_link($path)) throw new RuntimeException('Lien symbolique refusé pendant le rollback.');
        if(is_file($path)){ if(!unlink($path)) throw new RuntimeException('Impossible de supprimer '.$path); return; }
        if(!is_dir($path)) return;
        foreach(scandir($path)?:[] as $entry){ if($entry==='.'||$entry==='..') continue; self::removeTree($path.'/'.$entry); }
        if(!rmdir($path)) throw new RuntimeException('Impossible de supprimer '.$path);
    }

    private static function snapshotDatabase(string $source, string $destination): void
    {
        if (!is_dir(dirname($destination))
            && !mkdir(dirname($destination), 0700, true)
            && !is_dir(dirname($destination))) {
            throw new RuntimeException('Impossible de préparer la sauvegarde SQLite.');
        }
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            throw new RuntimeException('PDO_SQLITE est requis pour sauvegarder la base avant mise à jour.');
        }
        $pdo = new PDO('sqlite:' . $source, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('PRAGMA busy_timeout = 5000');
        $quotedDestination = $pdo->quote($destination);
        if (!is_string($quotedDestination)) {
            throw new RuntimeException('Impossible de préparer le chemin de sauvegarde SQLite.');
        }
        $pdo->exec('VACUUM INTO ' . $quotedDestination);
        $pdo = null;
        if (!is_file($destination) || filesize($destination) === 0) {
            throw new RuntimeException('La copie de sauvegarde SQLite est absente ou vide.');
        }
    }

    /** @param list<string> $excludedTopLevel */
    private static function copyTree(string $source, string $destination, array $excludedTopLevel = []): void
    {
        if (!is_dir($source)) {
            return;
        }
        if (is_link($source)) {
            throw new RuntimeException('Lien symbolique refusé dans les données à sauvegarder.');
        }
        if (!is_dir($destination) && !mkdir($destination, 0700, true) && !is_dir($destination)) {
            throw new RuntimeException('Impossible de créer un dossier dans la sauvegarde.');
        }
        $entries = scandir($source);
        if ($entries === false) {
            throw new RuntimeException('Impossible de lire les données à sauvegarder.');
        }
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || in_array($entry, $excludedTopLevel, true)) {
                continue;
            }
            $from = $source . '/' . $entry;
            $to = $destination . '/' . $entry;
            if (is_link($from)) {
                throw new RuntimeException('Lien symbolique refusé dans les données à sauvegarder : ' . $entry);
            }
            if (is_dir($from)) {
                self::copyTree($from, $to);
                continue;
            }
            if (!is_file($from)) {
                continue;
            }
            if (!copy($from, $to)) {
                throw new RuntimeException('Impossible de sauvegarder : ' . $from);
            }
        }
    }

    private static function estimateBackupBytes(string $root): int
    {
        $total = is_file($root . '/' . SiteConfiguration::load($root)->databasePath())
            ? (int) filesize($root . '/' . SiteConfiguration::load($root)->databasePath())
            : 0;
        foreach ([
            [$root . '/var/sys', ['maintenance.lock']],
            [$root . '/var/uploads/images', []],
            [$root . '/var/private', ['update-backups']],
            [$root . '/download', []],
        ] as [$path, $excluded]) {
            $total += self::treeBytes($path, $excluded);
        }
        return $total;
    }

    /** @param list<string> $excludedTopLevel */
    private static function treeBytes(string $directory, array $excludedTopLevel): int
    {
        if (!is_dir($directory) || is_link($directory)) {
            return 0;
        }
        $entries = scandir($directory);
        if ($entries === false) {
            return 0;
        }
        $total = 0;
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || in_array($entry, $excludedTopLevel, true)) {
                continue;
            }
            $path = $directory . '/' . $entry;
            if (is_link($path)) {
                continue;
            }
            if (is_dir($path)) {
                $total += self::treeBytes($path, []);
            } elseif (is_file($path)) {
                $total += (int) filesize($path);
            }
        }
        return $total;
    }

    /** @return array{0:string,1:string} */
    private static function uniqueBackupPaths(string $parent, string $baseName): array
    {
        $suffix = 1;
        do {
            $name = $baseName . ($suffix === 1 ? '' : '-' . $suffix);
            $final = $parent . '/' . $name;
            $partial = $parent . '/.partial-' . $name;
            $suffix++;
        } while (file_exists($final) || file_exists($partial));
        return [$partial, $final];
    }

    private static function relativePath(string $root, string $path): string
    {
        $prefix = rtrim($root, '/\\') . '/';
        return str_starts_with($path, $prefix) ? substr($path, strlen($prefix)) : $path;
    }
}

