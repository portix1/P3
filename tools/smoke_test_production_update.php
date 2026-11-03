<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Ce test doit être exécuté en ligne de commande.\n");
    exit(1);
}

$root = dirname(__DIR__);
require $root . '/bootstrap/autoload.php';

$temporaryRoot = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR
    . 'portix3-production-update-' . getmypid() . '-' . bin2hex(random_bytes(4));

try {
    foreach ([
        'database',
        'var/sys',
        'var/uploads/images',
        'var/private/downloads',
    ] as $directory) {
        if (!mkdir($temporaryRoot . '/' . $directory, 0770, true) && !is_dir($temporaryRoot . '/' . $directory)) {
            throw new RuntimeException('Impossible de préparer le site de test : ' . $directory);
        }
    }
    file_put_contents($temporaryRoot . '/var/sys/config.json', "{\"database_file\":\"atelier-test.sqlite\"}\n");
    file_put_contents($temporaryRoot . '/var/sys/contact.json', "{\"recipient_name\":\"Test\"}\n");
    file_put_contents($temporaryRoot . '/var/uploads/images/test.webp', 'image-test');
    file_put_contents($temporaryRoot . '/var/private/downloads/test.bin', 'download-test');

    if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
        file_put_contents($temporaryRoot . '/database/atelier-test.sqlite', 'sqlite-placeholder');
    } else {
        $pdo = new PDO('sqlite:' . $temporaryRoot . '/database/atelier-test.sqlite', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $pdo->exec('CREATE TABLE proof (value TEXT NOT NULL)');
        $pdo->exec("INSERT INTO proof (value) VALUES ('production-backup')");
        $pdo = null;
    }

    Portix\Maintenance\UpdateSafety::assertInstalled($temporaryRoot);
    Portix\Maintenance\UpdateSafety::markRelease($temporaryRoot, '0.7.1', 'install');
    updateAssertSame(
        '0.7.1',
        Portix\Maintenance\UpdateSafety::installedRelease($temporaryRoot),
        'version installée'
    );
    Portix\Maintenance\UpdateSafety::enableMaintenance($temporaryRoot, '0.7.2');
    updateAssertTrue(
        is_file($temporaryRoot . '/var/sys/maintenance.lock'),
        'mode maintenance activé'
    );

    if (in_array('sqlite', PDO::getAvailableDrivers(), true)) {
        $backup = Portix\Maintenance\UpdateSafety::createBackup($temporaryRoot, '0.7.2');
        foreach ([
            'database/portix2.sqlite',
            'var/sys/config.json',
            'var/sys/contact.json',
            'var/uploads/images/test.webp',
            'var/private/downloads/test.bin',
            'manifest.json',
        ] as $relative) {
            updateAssertTrue(is_file($backup . '/' . $relative), 'sauvegarde ' . $relative);
        }
        updateAssertTrue(
            !str_contains($backup, '.partial-'),
            'dossier de sauvegarde finalisé'
        );
        updateAssertTrue(
            !is_file($backup . '/var/sys/maintenance.lock'),
            'verrou de maintenance exclu de la sauvegarde'
        );
        $backupPdo = new PDO('sqlite:' . $backup . '/database/portix2.sqlite');
        updateAssertSame(
            'production-backup',
            (string) $backupPdo->query('SELECT value FROM proof')->fetchColumn(),
            'snapshot SQLite cohérent'
        );
        $backupPdo = null;
    }

    Portix\Maintenance\UpdateSafety::disableMaintenance($temporaryRoot);
    updateAssertTrue(
        !is_file($temporaryRoot . '/var/sys/maintenance.lock'),
        'mode maintenance désactivé'
    );
    Portix\Maintenance\UpdateSafety::markRelease($temporaryRoot, '0.7.2', 'update');
    updateAssertSame(
        '0.7.2',
        Portix\Maintenance\UpdateSafety::installedRelease($temporaryRoot),
        'version mise à jour'
    );

    $frontController = (string) file_get_contents($root . '/index.php');
    updateAssertTrue(
        str_contains($frontController, 'maintenance.lock')
            && str_contains($frontController, 'http_response_code(503)'),
        'réponse HTTP 503 pendant la maintenance'
    );

    echo in_array('sqlite', PDO::getAvailableDrivers(), true)
        ? "Mise à jour production, sauvegarde et suivi de version : OK\n"
        : "Mise à jour production et suivi de version : OK (snapshot SQLite non exécuté sans PDO_SQLITE)\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Test mise à jour production : ÉCHEC - ' . $error->getMessage() . "\n");
    exit(1);
} finally {
    updateRemoveTree($temporaryRoot);
}

function updateAssertTrue(bool $condition, string $label): void
{
    if (!$condition) {
        throw new RuntimeException('Assertion échouée : ' . $label);
    }
}

function updateAssertSame(mixed $expected, mixed $actual, string $label): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            'Assertion échouée : ' . $label . ' (attendu ' . var_export($expected, true)
            . ', obtenu ' . var_export($actual, true) . ')'
        );
    }
}

function updateRemoveTree(string $directory): void
{
    if (!is_dir($directory)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $entry) {
        $entry->isDir() ? @rmdir($entry->getPathname()) : @unlink($entry->getPathname());
    }
    @rmdir($directory);
}
