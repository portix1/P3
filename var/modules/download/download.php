<?php
declare(strict_types=1);

use PortixModule\Download\DownloadResponse;
use Portix\Support\Translator;

require_once __DIR__ . '/src/DownloadResponse.php';

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

if (!isset($_GET['id']) || !is_string($_GET['id']) || !ctype_digit($_GET['id'])) {
    http_response_code(400);
    exit($translator->text('downloads_invalid_url'));
}
DownloadResponse::send(
    $application->repositories()->downloads(),
    $translator,
    $root,
    (int) $_GET['id'],
    (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')
);
