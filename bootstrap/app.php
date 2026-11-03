<?php
declare(strict_types=1);

require_once __DIR__ . '/autoload.php';

set_exception_handler(static function (Throwable $error): void {
    error_log((string) $error);
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
    }
    echo '<!doctype html><html lang="fr"><meta charset="utf-8"><title>Erreur Portix 3</title>'
        . '<body><h1>Erreur Portix 3</h1><p>Le démarrage de l’application a échoué. '
        . 'Consultez le journal PHP.</p></body></html>';
});

return Portix\Core\Bootstrap::boot(dirname(__DIR__));
