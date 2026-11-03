<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit(1); }
require dirname(__DIR__) . '/bootstrap/autoload.php';
require dirname(__DIR__) . '/includes/class/security.class.php';
function themeAdminCopy(string $source, string $target): void {
    if (!is_dir($target)) { mkdir($target, 0700, true); }
    foreach (new FilesystemIterator($source, FilesystemIterator::SKIP_DOTS) as $file) {
        if ($file->isDir()) { themeAdminCopy($file->getPathname(), $target . '/' . $file->getFilename()); }
        else { copy($file->getPathname(), $target . '/' . $file->getFilename()); }
    }
}
function themeAdminRemove(string $directory): void {
    foreach (new FilesystemIterator($directory, FilesystemIterator::SKIP_DOTS) as $file) {
        $file->isDir() ? themeAdminRemove($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($directory);
}
function themeAdminAssert(bool $ok, string $label): void { if (!$ok) { throw new RuntimeException($label); } }
function themeAdminRender(string $root, array $post = [], string $method = 'POST', bool $admin = true, string $lang = 'fr'): string {
    $_LANG = []; require $root . '/var/lang/' . $lang . '.php';
    $translator = new Portix\Support\Translator($_LANG);
    $user = new class($admin) { public function __construct(private bool $admin) {} public function isAdmin(): bool { return $this->admin; } };
    $CONFIG = ['lang' => $lang, 'template' => 'six-cordes', 'defaultpage' => 'news.html', 'title' => 'Test thèmes'];
    $template = new Portix\View\Template($root . '/var/admin/templates/themes.tpl');
    foreach ($_LANG as $key => $value) { $template->set($key, $value); }
    $template->set('csrf_token', 'fixture-token');
    $_POST = $post; $_SERVER['REQUEST_METHOD'] = $method;
    require $root . '/includes/themes.php';
    return $template->render();
}
$source = dirname(__DIR__); $temp = sys_get_temp_dir() . '/p3-theme-admin-' . bin2hex(random_bytes(6));$status = 0;$cwd = getcwd();
try {
    mkdir($temp . '/includes', 0700, true);copy($source . '/includes/config.php', $temp . '/includes/config.php');copy($source . '/includes/themes.php', $temp . '/includes/themes.php');copy($source . '/VERSION', $temp . '/VERSION');
    foreach (['var/admin/templates', 'var/lang', 'var/templates/six-cordes', 'var/templates/shared'] as $path) { themeAdminCopy($source . '/' . $path, $temp . '/' . $path); }
    chdir($temp); $_SESSION = ['csrf_token' => 'fixture-token'];
    $version = trim((string) file_get_contents($source . '/VERSION'));$manager = new Portix\Theme\ThemePackageManager($temp, $version);
    $manifest = json_decode((string) file_get_contents($temp . '/var/templates/six-cordes/theme.json'), true, 512, JSON_THROW_ON_ERROR);$manifest['version'] = '0.1.2';
    $zipPath = $temp . '/theme.zip';$zip = new ZipArchive();$zip->open($zipPath, ZipArchive::CREATE);
    $zip->addFromString('six-cordes/theme.json', json_encode($manifest, JSON_THROW_ON_ERROR));
    foreach ($manifest['files'] as $path => $_) { $zip->addFile($source . '/var/templates/six-cordes/' . $path, 'six-cordes/' . $path); }$zip->close();
    $rendered = ['inventory' => themeAdminRender($temp, [], 'GET')];
    themeAdminAssert(str_contains($rendered['inventory'], '0.1.1'), 'Version dans le sélecteur');
    themeAdminAssert(!str_contains($rendered['inventory'], '<option value="shared"'), 'Shared non sélectionnable');
    $plan = $manager->stage($zipPath);$_SESSION['theme_package_token'] = $plan['token'];$rendered['plan'] = themeAdminRender($temp, [], 'GET');
    $post = ['themes_action' => 'install', 'theme_token' => $plan['token'], 'themes_confirm' => 'yes', 'csrf_token' => 'fixture-token'];
    $badCsrf = $post;$badCsrf['csrf_token'] = 'wrong';$html = themeAdminRender($temp, $badCsrf);
    themeAdminAssert(str_contains($html, 'Jeton de formulaire invalide'), 'CSRF refusé');
    $html = themeAdminRender($temp, $post, 'GET');themeAdminAssert(str_contains($html, 'requête POST'), 'GET refusé');
    $badToken = $post;$badToken['theme_token'] = str_repeat('a',32);$html = themeAdminRender($temp, $badToken);themeAdminAssert(str_contains($html, 'non rattachée'), 'Jeton lié à la session');
    $noConfirm = $post;unset($noConfirm['themes_confirm']);$html = themeAdminRender($temp, $noConfirm);themeAdminAssert(str_contains($html, 'confirmation de cette opération'), 'Confirmation obligatoire');
    $refused = false;try { themeAdminRender($temp, $post, 'POST', false); } catch (RuntimeException $error) { $refused = $error->getMessage() === 'Accès refusé.'; }themeAdminAssert($refused, 'Administrateur requis');
    themeAdminAssert((new Portix\Theme\ThemeCatalog($temp, $version))->inspect('six-cordes')['version'] === '0.1.1', 'Refus sans écriture de thème');
    $html = themeAdminRender($temp, $post);themeAdminAssert(str_contains($html, 'Thème installé'), 'Installation confirmée');
    themeAdminAssert(!isset($_SESSION['theme_package_token']), 'Analyse consommée');
    $rendered['backups'] = themeAdminRender($temp, [], 'GET');$receipt = $manager->backups()[0];
    $restorePost = ['themes_action' => 'restore', 'backup_token' => $receipt['token'], 'themes_confirm' => 'yes', 'csrf_token' => 'fixture-token'];
    $html = themeAdminRender($temp, $restorePost);themeAdminAssert(str_contains($html, 'Version précédente restaurée'), 'Restauration confirmée');
    file_put_contents($temp . '/var/templates/six-cordes/style.css', '\n/* locale */', FILE_APPEND);$plan = $manager->stage($zipPath);$_SESSION['theme_package_token'] = $plan['token'];
    $rendered['conflict'] = themeAdminRender($temp, [], 'GET');themeAdminAssert(!str_contains($rendered['conflict'], 'value="install"'), 'Conflit sans bouton installer');
    $html = themeAdminRender($temp, ['themes_action' => 'cancel', 'theme_token' => $plan['token'], 'csrf_token' => 'fixture-token']);themeAdminAssert(str_contains($html, 'Analyse annulée'), 'Annulation');
    $rendered['english'] = themeAdminRender($temp, [], 'GET', true, 'en');
    foreach ($rendered as $name => $html) {
        themeAdminAssert(!str_contains($html, '{{') && !str_contains($html, '{%'), 'Rendu strict ' . $name);
        if (!empty($argv[1]) && is_dir($argv[1])) { file_put_contents($argv[1] . '/' . $name . '.html', '<!doctype html><html lang="'.($name === 'english' ? 'en' : 'fr').'"><meta charset="utf-8"><meta name="viewport" content="width=device-width"><link rel="stylesheet" href="/var/admin/style.css"><body style="padding:1rem"><main class="admin-content">'.$html.'</main></body></html>'); }
    }
    echo "Administration thèmes : rendu FR/EN, administrateur, POST, CSRF, session, confirmation, installation, conflit, annulation et restauration : OK\n";
} catch (Throwable $error) { fwrite(STDERR, 'Test administration thèmes : ÉCHEC - ' . $error->getMessage() . "\n");$status = 1; }
finally { chdir($cwd);themeAdminRemove($temp); }
exit($status);
