<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Ce test doit être exécuté en ligne de commande.\n");
    exit(1);
}

$source = (string) file_get_contents(dirname(__DIR__) . '/includes/class/security.class.php');

$required = [
    "frame-src https://www.youtube-nocookie.com https://player.vimeo.com https://www.dailymotion.com;",
    "frame-ancestors 'none'",
    "object-src 'none'",
];

foreach ($required as $marker) {
    if (strpos($source, $marker) === false) {
        fwrite(STDERR, "CSP vidéo incomplète : {$marker}\n");
        exit(1);
    }
}

foreach ([
    'https://www.youtube.com',
    'https://youtube.com',
    'https://vimeo.com;',
] as $forbidden) {
    $frameSrc = strstr($source, 'frame-src ');
    $frameSrc = $frameSrc === false ? '' : strtok($frameSrc, '"');
    if ($frameSrc !== '' && strpos($frameSrc, $forbidden) !== false) {
        fwrite(STDERR, "CSP vidéo trop large : {$forbidden}\n");
        exit(1);
    }
}

echo "CSP vidéo : fournisseurs autorisés et cadre de sécurité conservé\n";
