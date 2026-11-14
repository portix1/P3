<?php
declare(strict_types=1);
use Portix\Maintenance\UpdateSafety;
use Portix\Support\Translator;
use Portix\View\Template;

require dirname(__DIR__) . '/bootstrap/autoload.php';
require_once dirname(__DIR__) . '/includes/class/security.class.php';

function rollbackCheck(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
}
function rollbackRemove(string $path): void
{
    if (is_dir($path) && !is_link($path)) {
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') { rollbackRemove($path . '/' . $entry); }
        }
        rmdir($path);
    } elseif (file_exists($path) || is_link($path)) { unlink($path); }
}
function rollbackRefused(callable $operation, string $message): void
{
    try { $operation(); } catch (RuntimeException) { return; }
    throw new RuntimeException($message);
}
function rollbackController(string $root, string $role, string $method, array $post, string $language = 'fr'): string
{
    $user = $role === 'anonymous' ? null : new class($role) {
        public function __construct(private string $role) {}
        public function isAdmin(): bool { return $this->role === 'admin'; }
        public function isRedactor(): bool { return $this->role === 'editor'; }
        public function pseudo(): string { return 'test-admin'; }
    };
    $repositories = new class {
        public function contentSafety(): object { return new stdClass(); }
    };
    $database = new class {
        public function connection(): PDO {
            return new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        }
    };
    $_LANG = [];
    require $root . '/var/modules/safety/lang/' . $language . '.php';
    $translator = new Translator($_LANG);
    $template = new Template('var/modules/safety/templates/rollback.tpl');
    foreach ($_LANG as $key => $value) { $template->set($key, $value); }
    $template->set('csrf_token', 'test-token');
    $argument = ['Admin', 'safety', 'rollback']; $requestId = 'test-request';
    $_SERVER['REQUEST_METHOD'] = $method;
    $_SESSION = ['csrf_token' => 'test-token']; $_POST = $post;
    require $root . '/var/modules/safety/config.php';
    return $template->render();
}

$root = sys_get_temp_dir() . '/p3-rollback-' . bin2hex(random_bytes(6));
$previousDirectory = getcwd();
try {
    foreach (['database', 'var/sys', 'var/uploads/images', 'var/private', 'src', 'bootstrap', 'includes', 'tools', 'var/admin', 'var/editor', 'var/lang', 'var/modules', 'var/templates'] as $directory) {
        mkdir($root . '/' . $directory, 0700, true);
    }
    file_put_contents($root . '/VERSION', '0.8.01-beta1-preview5');
    file_put_contents($root . '/src/proof.txt', 'original code');
    file_put_contents($root . '/var/sys/config.json', '{"database_file":"custom.sqlite"}');
    file_put_contents($root . '/var/uploads/images/proof.webp', 'original image');
    $pdo = new PDO('sqlite:' . $root . '/database/custom.sqlite', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec("CREATE TABLE proof (value TEXT); INSERT INTO proof VALUES ('original database')"); $pdo = null;
    UpdateSafety::markRelease($root, '0.7.09-rc16', 'install');
    $point = UpdateSafety::createRollbackPoint($root); $name = basename($point);
    $manifest = json_decode((string) file_get_contents($point . '/manifest.json'), true, 64, JSON_THROW_ON_ERROR);
    rollbackCheck($manifest['previous_version'] === '0.8.01-beta1-preview5', 'New point must record saved VERSION.');
    $manifest['previous_version'] = '0.7.09-rc16';
    file_put_contents($point . '/manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR));
    $savedManifest = file_get_contents($point . '/manifest.json');
    rollbackCheck(UpdateSafety::listRollbackPoints($root)[0]['version'] === '0.8.01-beta1-preview5', 'Existing point must use its own VERSION.');
    rollbackCheck(file_get_contents($point . '/manifest.json') === $savedManifest, 'Listing rewrote historical manifest.');
    unlink($point . '/application/VERSION');
    rollbackCheck(UpdateSafety::listRollbackPoints($root)[0]['version'] === '0.7.09-rc16', 'Legacy manifest fallback failed.');
    file_put_contents($point . '/application/VERSION', '0.7.08');
    rollbackCheck(UpdateSafety::listRollbackPoints($root)[0]['version'] === '0.7.08', 'Old points must not use the live version.');
    file_put_contents($point . '/application/VERSION', '0.8.01-beta1-preview5');
    echo "PASS saved version, historical correction without rewriting, and legacy fallback.\n";

    file_put_contents($root . '/var/private/.htaccess', 'Require all denied');
    file_put_contents($root . '/var/sys/core-release-public.pem', 'current trust anchor');
    file_put_contents($point . '/var/sys/core-release-public.pem', 'obsolete trust anchor');
    file_put_contents($root . '/VERSION', '0.8.3-rc3'); file_put_contents($root . '/src/proof.txt', 'new code');
    file_put_contents($root . '/var/uploads/images/proof.webp', 'new image');
    $pdo = new PDO('sqlite:' . $root . '/database/custom.sqlite'); $pdo->exec("UPDATE proof SET value='new database'"); $pdo = null;
    UpdateSafety::restoreRollbackPoint($root, $name);
    rollbackCheck(trim((string) file_get_contents($root . '/VERSION')) === '0.8.01-beta1-preview5', 'Saved VERSION not restored.');
    rollbackCheck(UpdateSafety::installedRelease($root) === '0.8.01-beta1-preview5', 'Stale tracking not reconciled.');
    rollbackCheck(file_get_contents($root . '/src/proof.txt') === 'original code', 'Code restoration failed.');
    rollbackCheck(file_get_contents($root . '/var/uploads/images/proof.webp') === 'original image', 'Media restoration failed.');
    $pdo = new PDO('sqlite:' . $root . '/database/custom.sqlite');
    rollbackCheck($pdo->query('SELECT value FROM proof')->fetchColumn() === 'original database', 'Custom SQLite restoration failed.'); $pdo = null;
    $points = UpdateSafety::listRollbackPoints($root);
    rollbackCheck(count($points) === 2 && in_array('0.8.3-rc3', array_column($points, 'version'), true), 'Rescue point missing or wrong version.');
    rollbackCheck(!is_file($root . '/var/sys/maintenance.lock'), 'Maintenance remains active.');
    rollbackCheck(file_get_contents($root . '/var/private/.htaccess') === 'Require all denied', 'Restoration removed private protection.');
    rollbackCheck(file_get_contents($root . '/var/sys/core-release-public.pem') === 'current trust anchor', 'Restoration replaced current trust anchor.');
    echo "PASS full restoration, custom SQLite path, rescue point and release tracking.\n";

    $parent = dirname($point);
    foreach (['..', '../sys', '.', '.partial-test', 'missing'] as $invalid) {
        rollbackRefused(static fn() => UpdateSafety::deleteRollbackPoint($root, $invalid), 'Unsafe name accepted.');
    }
    mkdir($parent . '/automatic-backup'); file_put_contents($parent . '/automatic-backup/manifest.json', '{"target_version":"0.8.3"}');
    rollbackRefused(static fn() => UpdateSafety::deleteRollbackPoint($root, 'automatic-backup'), 'Data-only backup accepted.');
    symlink($root . '/src', $point . '/application/linked-directory');
    rollbackRefused(static fn() => UpdateSafety::deleteRollbackPoint($root, $name), 'Linked descendant accepted.');
    rollbackCheck(is_file($point . '/application/VERSION'), 'Linked point partly erased.');
    unlink($point . '/application/linked-directory');
    echo "PASS invalid names, data-only backups and symlinks refused before deletion.\n";

    $before = [hash_file('sha256', $root . '/database/custom.sqlite'), file_get_contents($root . '/src/proof.txt'), file_get_contents($root . '/var/sys/config.json')];
    UpdateSafety::deleteRollbackPoint($root, $name);
    rollbackCheck(!file_exists($point) && count(UpdateSafety::listRollbackPoints($root)) === 1, 'Deletion affected wrong points.');
    rollbackCheck($before === [hash_file('sha256', $root . '/database/custom.sqlite'), file_get_contents($root . '/src/proof.txt'), file_get_contents($root . '/var/sys/config.json')], 'Deletion modified the live site.');
    echo "PASS only selected point deleted; live site and other points unchanged.\n";

    $module = dirname(__DIR__) . '/var/modules/safety';
    mkdir($root . '/var/modules/safety/templates', 0700, true); mkdir($root . '/var/modules/safety/lang', 0700, true);
    foreach (['config.php', 'templates/rollback.tpl', 'templates/nav.tpl', 'lang/fr.php', 'lang/en.php'] as $file) {
        copy($module . '/' . $file, $root . '/var/modules/safety/' . $file);
    }
    chdir($root); $remaining = UpdateSafety::listRollbackPoints($root)[0]['name'];
    $post = ['safety_action'=>'delete_point', 'name'=>$remaining, 'csrf_token'=>'test-token', 'confirm_delete_point'=>'yes'];
    foreach (['anonymous', 'editor'] as $role) {
        rollbackRefused(static fn() => rollbackController($root, $role, 'POST', $post), 'Non-admin deletion accepted.');
    }
    foreach ([['GET', $post], ['POST', array_replace($post, ['csrf_token'=>'wrong'])], ['POST', array_replace($post, ['confirm_delete_point'=>''])]] as [$method, $invalidPost]) {
        rollbackController($root, 'admin', $method, $invalidPost);
        rollbackCheck(count(UpdateSafety::listRollbackPoints($root)) === 1, 'Unprotected request deleted a point.');
    }
    foreach (['fr', 'en'] as $language) {
        $html = rollbackController($root, 'admin', 'GET', [], $language);
        rollbackCheck(str_contains($html, 'name="confirm_delete_point" value="yes" required'), 'Confirmation absent from template.');
        rollbackCheck(str_contains($html, '0.8.3-rc3'), 'Saved version missing from table.');
    }
    $html = rollbackController($root, 'admin', 'POST', $post);
    rollbackCheck(str_contains($html, 'Point de restauration supprimé définitivement.') && UpdateSafety::listRollbackPoints($root) === [], 'Confirmed controller deletion failed.');
    echo "PASS controller: admin-only POST, CSRF, explicit confirmation, French/English rendering.\n";
} finally {
    if (is_string($previousDirectory)) { chdir($previousDirectory); }
    rollbackRemove($root);
}
echo "Rollback : OK\n";

