<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$root = realpath($argv[1] ?? '');
if ($root === false || !is_file($root . '/VERSION')) { fwrite(STDERR, "Indiquez le dossier de la copie RC15 cible.\n"); exit(2); }
$expected = [
    'VERSION' => '58659c3a6b2add488d4ddcba4c85fea1da6e248bf225a049c2e2160b42bb3c2e',
    'includes/class/html_sanitizer.class.php' => 'ac7756578eb2744d63d85f36598ec106927faf9d5190ea6043723ffb3fb5175b',
    'includes/class/image_storage.class.php' => '31f1d7e9eb4795e740ff04cb9ca8b0e462b5674aa3b31a2a82392716b6624cb6',
    'src/Core/Application.php' => '32ca529903ba560fae049b9dbb70aec3b203cdd8ae7e4778e3d902bff5634b33',
    'tools/validate_release.php' => '02e06cbb5b46d1455c76117938c4f9c242194bc88479e78c4ca365b5fd6d6bcf',
    'var/admin/icons.css' => '35141adf8808073cda75fa9b22d08dc8c5c5ed4771d069ae5a604571ebae3611',
    'var/admin/icons.js' => '8458152c4cb7d90142e8181566c744c26282daf46deeb57de4fbc47781b5ec31',
    'var/admin/index.tpl' => '4f1ce108ac570046ab76cbd20d9149e337eac352c0f0ec4405a48a527ebb4d2e',
    'var/admin/templates/diagnostics.tpl' => 'a3a875ead39a6ea97f2e58fab9358096c22903c9b3fbb5277c415750916abb1f',
    'var/editor/portix-editor.js' => '6459f0d25ab14c7a4e4e90b3d90eb608a1acdf4bf154b1c9eef0176a52cdcc81',
    'var/sys/release.json' => 'a6fb31f7d7a247a7dcae145ae42452a0bc2356a8a89d84eefa173c044a615731',
];
$failed = false;
foreach ($expected as $relative => $hash) {
    $file = $root . '/' . $relative;
    if (!is_file($file) || !hash_equals($hash, (string) hash_file('sha256', $file))) { echo 'DIFFÉRENT OU ABSENT : ' . $relative . PHP_EOL; $failed = true; }
}
$newFiles = [
    'src/Media/MediaBridge.php',
    'tools/check_media_update.php',
    'tools/smoke_test_media.php',
    'tools/smoke_test_media_registration.php',
    'tools/test_media_http_wasm.mjs',
    'tools/test_media_manager_browser.cjs',
    'tools/test_media_selector_browser.cjs',
    'var/modules/media/MODULE-VERSION',
    'var/modules/media/assets/media.css',
    'var/modules/media/assets/media.js',
    'var/modules/media/assets/public-media.htaccess',
    'var/modules/media/config.php',
    'var/modules/media/database/media_items/desc.csv',
    'var/modules/media/desc.xml',
    'var/modules/media/index.php',
    'var/modules/media/src/MediaManager.php',
    'var/modules/media/src/MediaUsage.php',
    'var/modules/media/templates/index.tpl',
];
foreach ($newFiles as $relative) { if (file_exists($root . '/' . $relative)) { echo 'COLLISION : ' . $relative . PHP_EOL; $failed = true; } }
foreach (['pdo_sqlite','gd','mbstring','dom','fileinfo','zip'] as $extension) { if (!extension_loaded($extension)) { echo 'EXTENSION ABSENTE : ' . $extension . PHP_EOL; $failed = true; } }
if (PHP_VERSION_ID < 80200) { echo 'PHP 8.2 minimum requis.' . PHP_EOL; $failed = true; }
echo 'PHP : ' . PHP_VERSION . '; upload_max_filesize=' . ini_get('upload_max_filesize') . '; post_max_size=' . ini_get('post_max_size') . PHP_EOL;
echo $failed ? 'ARRÊT : ne pas appliquer le paquet avant examen des différences.' . PHP_EOL : 'Sources conformes à BACKUP-UG.zip. Ce contrôle ne valide pas la production.' . PHP_EOL;
exit($failed ? 1 : 0);
