<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit(1); }
require_once dirname(__DIR__) . '/bootstrap/autoload.php';
use Portix\Theme\ThemeCatalog;
use Portix\Theme\ThemeManifest;
function themeAssert(bool $condition, string $label): void { if (!$condition) { throw new RuntimeException($label); } }
function themeRemove(string $path): void { if (!is_dir($path)) { return; } foreach (new FilesystemIterator($path, FilesystemIterator::SKIP_DOTS) as $file) { $file->isDir() && !$file->isLink() ? themeRemove($file->getPathname()) : unlink($file->getPathname()); } rmdir($path); }
$root = dirname(__DIR__); $temporary = sys_get_temp_dir() . '/p3-theme-manifest-' . bin2hex(random_bytes(6));
try {
    $version = trim((string) file_get_contents($root . '/VERSION'));
    $catalog = new ThemeCatalog($root, $version);
    foreach ($catalog->all() as $entry) {
        if (!$entry['versioned'] && $entry['errors'] === []) { continue; }
        themeAssert($entry['versioned'] && $entry['compatible'] === true, 'Thème livré non versionné ou incompatible : ' . $entry['id'] . ' ' . implode('; ', $entry['errors']));
        themeAssert($entry['changed'] === [] && $entry['missing'] === [], 'Empreintes initiales incohérentes : ' . $entry['id']);
    }
    foreach (['six-cordes', 'shared'] as $id) {
        $target = $temporary . '/var/templates/' . $id;
        mkdir($target, 0700, true);
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/var/templates/' . $id, FilesystemIterator::SKIP_DOTS)) as $file) {
            if (!$file->isFile()) { continue; }
            $relative = substr($file->getPathname(), strlen($root . '/var/templates/' . $id) + 1);
            if (!is_dir(dirname($target . '/' . $relative))) { mkdir(dirname($target . '/' . $relative), 0700, true); }
            copy($file->getPathname(), $target . '/' . $relative);
        }
    }
    $catalog = new ThemeCatalog($temporary, $version);
    $six = $catalog->inspect('six-cordes');
    themeAssert($six['compatible'] === true && $six['variant_of'] === 'univers-guitare', 'Variante autonome sans thème parent installé');
    $css = $temporary . '/var/templates/six-cordes/style.css'; file_put_contents($css, "\n/* Personnalisation locale */", FILE_APPEND);
    $six = $catalog->inspect('six-cordes');
    themeAssert($six['changed'] === ['style.css'] && $six['compatible'] === true, 'Modification locale détectée sans désactivation');
    file_put_contents(dirname($css) . '/personnel.css', 'p{color:red}');
    themeAssert(in_array('personnel.css', $catalog->inspect('six-cordes')['extra'], true), 'Fichier supplémentaire identifié et conservé');
    unlink($temporary . '/var/templates/shared/breadcrumb.tpl');
    themeAssert($catalog->inspect('six-cordes')['compatible'] === false, 'Dépendance shared manquante refusée');
    copy($root . '/var/templates/shared/breadcrumb.tpl', $temporary . '/var/templates/shared/breadcrumb.tpl');
    themeAssert((new ThemeCatalog($temporary, '0.7.09-rc15'))->inspect('six-cordes')['compatible'] === false, 'Moteur trop ancien');
    themeAssert((new ThemeCatalog($temporary, $version, '8.1'))->inspect('six-cordes')['compatible'] === false, 'PHP trop ancien');
    themeAssert(!ThemeManifest::validPath('../secret.css') && !ThemeManifest::validPath('/secret.css') && !ThemeManifest::validPath('a.php') && !ThemeManifest::validPath('assets/../../a.css'), 'Chemins refusés');
    mkdir($temporary . '/var/templates/legacy');file_put_contents($temporary . '/var/templates/legacy/index.tpl', '<h1>Ancien thème</h1>');
    $legacy = $catalog->inspect('legacy');themeAssert(!$legacy['versioned'] && $legacy['compatible'] === null, 'Ancien thème conservé sans fausse version');
    $manifestPath = $temporary . '/var/templates/six-cordes/theme.json'; $manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
    foreach (['identity', 'hash', 'path', 'contract'] as $case) {
        $bad = $manifest;
        if ($case === 'identity') { $bad['id'] = 'other'; }
        if ($case === 'hash') { $bad['files']['style.css'] = 'no'; }
        if ($case === 'path') { $bad['files']['../secret.css'] = str_repeat('a', 64); }
        if ($case === 'contract') { $bad['contract'] = '999'; }
        file_put_contents($manifestPath, json_encode($bad));themeAssert($catalog->inspect('six-cordes')['compatible'] === false, 'Manifeste invalide refusé : ' . $case);
    }
    themeAssert(file_exists($css) && str_contains((string) file_get_contents($css), 'Personnalisation locale'), 'Audit sans écrasement');
    echo "Manifestes, versions, dépendances, empreintes et variantes de thèmes : OK\n";
} catch (Throwable $error) { fwrite(STDERR, 'Test manifestes thèmes : ÉCHEC - ' . $error->getMessage() . "\n"); exit(1); }
finally { themeRemove($temporary); }
