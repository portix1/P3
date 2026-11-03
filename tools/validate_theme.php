<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
require $root . '/bootstrap/autoload.php';

$theme = trim((string) ($argv[1] ?? '--all'));
$results = $theme === '--all'
    ? \Portix\Theme\ThemeContract::validateAll($root)
    : [$theme => \Portix\Theme\ThemeContract::validate($root, $theme)];

$failed = false;
foreach ($results as $name => $errors) {
    if ($errors === []) {
        echo '[OK] ' . $name . ' — contrat de thème v' . \Portix\Theme\ThemeContract::VERSION . PHP_EOL;
        continue;
    }
    $failed = true;
    echo '[ECHEC] ' . $name . PHP_EOL;
    foreach ($errors as $error) {
        echo '  - ' . $error . PHP_EOL;
    }
}

exit($failed ? 1 : 0);
