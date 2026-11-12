<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Cet outil doit être exécuté en ligne de commande.\n");
}

$root = dirname(__DIR__);
$version = trim((string) file_get_contents($root . '/VERSION'));
$httpUrl = null;
foreach (array_slice($argv, 1) as $argument) {
    if (str_starts_with($argument, '--http=')) {
        $httpUrl = trim(substr($argument, strlen('--http=')));
        continue;
    }
    fwrite(STDERR, 'Option inconnue : ' . $argument . PHP_EOL);
    exit(2);
}

echo 'Validation Portix 3 ' . $version . ' — PHP ' . PHP_VERSION . PHP_EOL;
if (PHP_VERSION_ID < 80200) {
    fwrite(STDERR, "PHP 8.2 ou plus récent est requis.\n");
    exit(1);
}
$requiredExtensions = [
    'pdo', 'pdo_sqlite', 'session', 'dom', 'gd', 'iconv',
    'fileinfo', 'mbstring', 'zip',
];
$missingExtensions = array_values(array_filter(
    $requiredExtensions,
    static fn (string $extension): bool => !extension_loaded($extension)
));
if ($missingExtensions !== []) {
    fwrite(STDERR, 'Extensions PHP manquantes : ' . implode(', ', $missingExtensions) . PHP_EOL);
    exit(1);
}
if (!function_exists('imagewebp')) {
    fwrite(STDERR, "Le support WebP de PHP GD (imagewebp) est requis.\n");
    exit(1);
}
echo "Environnement PHP : OK\n";

$tests = [
    '../var/modules/insights/tests/smoke_test_daily_audience.php',
    'check_php_syntax.php',
    'smoke_test_database.php',
    'smoke_test_routing_menu.php',
    'smoke_test_admin_navigation.php',
    'smoke_test_module_extensions.php',
    'smoke_test_html_sanitizer.php',
    'smoke_test_public_css.php',
    'smoke_test_template.php',
    'smoke_test_theme_contract.php',
    'smoke_test_theme_manifest.php',
    'smoke_test_theme_packages.php',
    'smoke_test_theme_admin.php',
    'smoke_test_article_permissions.php',
    'smoke_test_static_cache.php',
    'smoke_test_accessibility.php',
    'smoke_test_security.php',
    'smoke_test_content_safety.php',
    'smoke_test_rollback.php',
    'smoke_test_rate_limiter.php',
    'smoke_test_password_reset.php',
    'smoke_test_mail_configuration.php',
    'smoke_test_documentation.php',
    'smoke_test_localization.php',
    'smoke_test_module_schema.php',
    'smoke_test_extension_integrity.php',
    'smoke_test_module_packages.php',
    'smoke_test_portix3_services.php',
    'smoke_test_breadcrumb.php',
    'smoke_test_editor_workflow.php',
    'smoke_test_sitemap.php',
    'smoke_test_seo.php',
    'smoke_test_image_storage.php',
    'smoke_test_production_update.php',
    'smoke_test_ziparchive.php',
    'smoke_test_installer.php',
];

if (is_file($root . '/var/modules/media/src/MediaManager.php')) { $tests[] = 'smoke_test_media.php'; }

if (is_file($root . '/var/modules/home/desc.xml')) { $tests[] = 'smoke_test_home.php'; $tests[] = 'smoke_test_home_http.php'; }

foreach ($tests as $test) {
    releaseRunPhp($root, $test);
}
if ($httpUrl !== null && $httpUrl !== '') {
    releaseRunPhp($root, 'smoke_test_http.php', [$httpUrl]);
} else {
    echo "Validation HTTP non demandée ; utilisez --http=http://localhost/P3 après installation.\n";
}

echo 'Validation automatisée de Portix 3 ' . $version . " : OK\n";

/** @param list<string> $arguments */
function releaseRunPhp(string $root, string $script, array $arguments = []): void
{
    $relative = 'tools' . DIRECTORY_SEPARATOR . $script;
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . DIRECTORY_SEPARATOR . $relative);
    foreach ($arguments as $argument) {
        $command .= ' ' . escapeshellarg($argument);
    }
    echo PHP_EOL . '== ' . $relative . ' ==' . PHP_EOL;
    passthru($command, $exitCode);
    if ($exitCode !== 0) {
        fwrite(STDERR, $relative . ' a échoué avec le code ' . $exitCode . '.' . PHP_EOL);
        exit($exitCode > 0 ? $exitCode : 1);
    }
}
