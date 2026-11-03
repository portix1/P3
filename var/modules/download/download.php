<?php
declare(strict_types=1);

use Portix\Storage\DownloadStorage;
use Portix\Support\Translator;

$root = dirname(__DIR__, 3);
$application = require $root . '/bootstrap/app.php';
$language = basename((string) $application->configuration()->get('lang', 'fr'));
$_LANG = [];
foreach ([$root . '/var/lang/' . $language . '.php', $root . '/var/modules/download/lang/' . $language . '.php'] as $file) {
    if (is_file($file)) {
        include $file;
    }
}
$translator = new Translator($_LANG);

if (!isset($_GET['id']) || !ctype_digit((string) $_GET['id'])) {
    http_response_code(400);
    exit($translator->text('downloads_invalid_url'));
}
PortixSecurity::sendHeaders();
$download = $application->repositories()->downloads()->find((int) $_GET['id'], true);
if ($download === null) {
    http_response_code(404);
    exit($translator->text('downloads_not_found'));
}
$storage = new DownloadStorage($root . '/var/private/downloads');
try {
    $path = $storage->path((string) $download['stored_name']);
} catch (Throwable) {
    http_response_code(404);
    exit($translator->text('downloads_not_found'));
}
if (!is_file($path)) {
    http_response_code(404);
    exit($translator->text('downloads_not_found'));
}

$displayName = PortixSecurity::safeFilename((string) $download['original_name']);
$mimeType = (string) $download['mime_type'];
if ($mimeType === '' || preg_match('!^[a-z0-9.+-]+/[a-z0-9.+-]+$!i', $mimeType) !== 1) {
    $mimeType = 'application/octet-stream';
}
header("Content-Disposition: attachment; filename*=UTF-8''" . rawurlencode($displayName));
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') {
    $application->repositories()->downloads()->incrementCounter((int) $download['id']);
    readfile($path);
}
