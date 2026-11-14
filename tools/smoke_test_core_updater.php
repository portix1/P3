<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit(1); }
$source = dirname(__DIR__);
require $source . '/bootstrap/autoload.php';

use Portix\Maintenance\CoreUpdater;
use Portix\Maintenance\UpdateSafety;

function coreTestAssert(bool $condition, string $label): void {
    if (!$condition) { throw new RuntimeException('Mise à jour du noyau : ' . $label); }
}
function coreTestRemove(string $directory): void {
    if (!is_dir($directory)) { return; }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $entry) {
        if ($entry->isDir() && !$entry->isLink()) { @rmdir($entry->getPathname()); }
        else { @unlink($entry->getPathname()); }
    }
    @rmdir($directory);
}
function coreTestWrite(string $root, string $file, string $bytes): void {
    $path = $root . '/' . $file;
    if (!is_dir(dirname($path))) { mkdir(dirname($path), 0770, true); }
    file_put_contents($path, $bytes);
}
function coreTestZip(string $name, array $entries): void {
    $zip = new ZipArchive();
    if ($zip->open($name, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Impossible de construire le ZIP de test');
    }
    foreach ($entries as $path => $bytes) { $zip->addFromString($path, $bytes); }
    $zip->close();
}

$site = sys_get_temp_dir() . '/p3-core-updater-test-' . bin2hex(random_bytes(5));
try {
    coreTestWrite($site, 'VERSION', "0.9.2\n");
    coreTestWrite($site, 'index.php', '<?php echo "old";');
    coreTestWrite($site, 'includes/engine.php', '<?php // old engine');
    coreTestWrite($site, 'src/Core/Application.php', '<?php // old application');
    coreTestWrite($site, 'var/admin/templates/dashboard.tpl', 'before update');
    coreTestWrite($site, 'var/modules/articles/database/articles/desc.csv', 'old schema');
    coreTestWrite($site, 'var/templates/custom/style.css', 'custom theme');
    coreTestWrite($site, 'var/sys/config.json', "{\"database_file\":\"test.sqlite\"}\n");
    coreTestWrite($site, '.htaccess', 'LWS production rewrite');
    coreTestWrite($site, 'robots.txt', 'local rules');
    coreTestWrite($site, 'var/uploads/images/local.webp', 'local image');
    coreTestWrite($site, 'var/private/sample.bin', 'private data');
    if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
        throw new RuntimeException('PDO_SQLITE est requis pour ce test');
    }
    mkdir($site . '/database', 0770, true);
    $pdo = new PDO('sqlite:' . $site . '/database/test.sqlite');
    $pdo->exec('CREATE TABLE proof (value TEXT)');
    $pdo->exec("INSERT INTO proof VALUES ('original')");
    $pdo = null;

    $work = $site . '/var/private/test-work';
    mkdir($work, 0700, true);
    $archive = $work . '/test.zip';
    $managedRules = [
        'P3/database/.htaccess' => 'Require all denied',
        'P3/sessions/.htaccess' => 'Require all denied',
        'P3/tmp/.htaccess' => 'Require all denied',
        'P3/var/sys/.htaccess' => 'Require all denied',
        'P3/var/uploads/images/.htaccess' => 'Options -Indexes',
    ];
    coreTestWrite($site, 'web.config', 'local IIS routing');
    coreTestWrite($site, 'var/modules/articles/.htaccess', 'local module policy');
    coreTestWrite($site, 'var/modules/articles/web.config', 'local module IIS policy');
    $normal = [
        'P3/VERSION' => "0.9.3\n",
        'P3/index.php' => '<?php echo "new";',
        'P3/includes/engine.php' => '<?php // new engine',
        'P3/src/Core/Application.php' => '<?php // new application',
        'P3/var/admin/templates/dashboard.tpl' => 'after update',
        'P3/var/modules/articles/database/articles/desc.csv' => 'new schema',
        'P3/.htaccess' => 'do not replace hosting rules',
        'P3/web.config' => 'do not replace local IIS routing',
        'P3/var/modules/articles/.htaccess' => 'do not replace local module policy',
        'P3/var/modules/articles/web.config' => 'do not replace local IIS policy',
        'P3/var/private/.htaccess' => 'Require all denied',
        'P3/robots.txt' => 'do not replace local rules',
        'P3/var/templates/custom/style.css' => 'do not replace custom theme',
        'P3/var/sys/config.json' => 'do not replace local config',
        'P3/var/uploads/images/local.webp' => 'do not replace media',
        'P3/database/test.sqlite' => 'do not replace SQLite',
    ];
    coreTestZip($archive, $normal + $managedRules);
    $point = CoreUpdater::applyVerifiedArchive($site, $archive, '0.9.3', $work);
    coreTestAssert(trim((string)file_get_contents($site . '/VERSION')) === '0.9.3', 'version installée');
    coreTestAssert(file_get_contents($site . '/var/admin/templates/dashboard.tpl') === 'after update', 'écran actualisé');
    coreTestAssert(file_get_contents($site . '/var/modules/articles/database/articles/desc.csv') === 'new schema', 'schéma du module actualisé');
    coreTestAssert(file_get_contents($site . '/.htaccess') === 'LWS production rewrite', 'règles LWS conservées');
    foreach ($managedRules as $entry => $bytes) {
        coreTestAssert(file_get_contents($site . '/' . substr($entry, 3)) === $bytes, 'protection installée : ' . $entry);
    }
    coreTestAssert(file_get_contents($site . '/web.config') === 'local IIS routing', 'règles IIS locales conservées');
    coreTestAssert(file_get_contents($site . '/var/modules/articles/.htaccess') === 'local module policy', 'règles module conservées');
    coreTestAssert(file_get_contents($site . '/var/modules/articles/web.config') === 'local module IIS policy', 'règles IIS module conservées');
    coreTestAssert(file_get_contents($site . '/robots.txt') === 'local rules', 'robots personnalisé conservé');
    coreTestAssert(file_get_contents($site . '/var/templates/custom/style.css') === 'custom theme', 'thème personnalisé conservé');
    coreTestAssert(file_get_contents($site . '/var/uploads/images/local.webp') === 'local image', 'médias conservés');
    coreTestAssert(file_get_contents($site . '/var/private/.htaccess') === 'Require all denied', 'protection privée installée');
    coreTestAssert(file_get_contents($site . '/var/private/sample.bin') === 'private data', 'données privées conservées');
    coreTestAssert(file_get_contents($site . '/var/sys/config.json') === "{\"database_file\":\"test.sqlite\"}\n", 'configuration conservée');
    $pdo = new PDO('sqlite:' . $site . '/database/test.sqlite');
    coreTestAssert($pdo->query('SELECT value FROM proof')->fetchColumn() === 'original', 'SQLite préservée');
    $pdo = null;
    coreTestAssert(is_file($site . '/var/private/update-backups/' . $point . '/manifest.json'), 'point de restauration créé');
    coreTestAssert(!is_file($site . '/var/sys/maintenance.lock'), 'maintenance désactivée');

    foreach ([
        ['P3/VERSION' => '0.9.4', 'P3/../../attack.php' => 'unsafe', 'P3/index.php' => 'bad', 'P3/includes/engine.php' => 'bad', 'P3/src/Core/Application.php' => 'bad'],
        ['P3/VERSION' => '0.9.4', 'P3/index.php' => 'bad', 'P3/includes/engine.php' => 'bad', 'P3/src/Core/Application.php' => 'bad'],
    ] as $i => $candidate) {
        $candidate['P3/var/private/.htaccess'] = 'Require all denied';
        if ($i === 1) { $candidate['P3/VERSION'] = '0.9.1'; }
        coreTestZip($archive, $candidate + $managedRules);
        try {
            CoreUpdater::applyVerifiedArchive($site, $archive, '0.9.4', $work);
            throw new RuntimeException('Archive interdite acceptée');
        } catch (RuntimeException $error) {
            if ($error->getMessage() === 'Archive interdite acceptée') { throw $error; }
        }
        coreTestAssert(trim((string)file_get_contents($site . '/VERSION')) === '0.9.3',
            'archive refusée sans modifier la version');
    }

    // Simulate a destination conflict AFTER several core files were changed.
    coreTestWrite($site, 'var/admin/templates/dashboard.tpl', 'preserve after failed install');
    mkdir($site . '/var/modules/safety/obstacle.php', 0770, true);
    $broken = [
        'P3/index.php' => 'unexpected new content',
        'P3/includes/engine.php' => 'unexpected new engine',
        'P3/src/Core/Application.php' => 'unexpected new app',
        'P3/var/admin/templates/dashboard.tpl' => 'unexpected new dashboard',
        'P3/var/private/.htaccess' => 'new protection',
        'P3/var/modules/safety/obstacle.php' => 'destination is a directory',
        'P3/VERSION' => '0.9.4',
    ];
    coreTestZip($archive, $broken + $managedRules);
    try {
        CoreUpdater::applyVerifiedArchive($site, $archive, '0.9.4', $work);
        throw new RuntimeException('Destination incompatible acceptée');
    } catch (RuntimeException $error) {
        if ($error->getMessage() === 'Destination incompatible acceptée') { throw $error; }
    }
    coreTestAssert(trim((string)file_get_contents($site . '/VERSION')) === '0.9.3', 'version restaurée après échec');
    coreTestAssert(file_get_contents($site . '/includes/engine.php') === '<?php // new engine', 'moteur restauré après échec');
    coreTestAssert(file_get_contents($site . '/var/admin/templates/dashboard.tpl') === 'preserve after failed install', 'interface restaurée après échec');
    coreTestAssert(file_get_contents($site . '/var/private/.htaccess') === 'Require all denied', 'protection privée restaurée');
    coreTestAssert(!is_file($site . '/var/sys/maintenance.lock'), 'maintenance coupée après échec');
    echo "Mise à jour P3 : sauvegarde, installation, données conservées, ZIP hostile et retour arrière : OK\n";
} finally {
    coreTestRemove($site);
}
