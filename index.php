<?php
declare(strict_types=1);

$maintenanceLock = __DIR__ . '/var/sys/maintenance.lock';
if (is_file($maintenanceLock)) {
    http_response_code(503);
    header('Content-Type: text/html; charset=UTF-8');
    header('Retry-After: 120');
    header('Cache-Control: no-store');
    echo '<!doctype html><html lang="fr"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>Maintenance</title></head><body><main>'
        . '<h1>Maintenance en cours</h1><p>Le site est momentanément indisponible pendant une mise à jour.</p>'
        . '</main></body></html>';
    exit;
}

/** @var Portix\Core\Application $application */
$application = require __DIR__ . '/bootstrap/app.php';
$application->run();
