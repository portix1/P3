<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Ce test doit être exécuté en ligne de commande.\n");
    exit(1);
}

$root = dirname(__DIR__);

try {
    $htaccess = (string) file_get_contents($root . '/.htaccess');
    foreach ([
        'ExpiresByType text/css "access plus 1 year"',
        'ExpiresByType application/javascript "access plus 1 year"',
        'ExpiresByType image/webp "access plus 1 year"',
        'Header set Cache-Control "public, max-age=31536000, immutable"',
        'AddOutputFilterByType DEFLATE',
    ] as $directive) {
        if (!str_contains($htaccess, $directive)) {
            throw new RuntimeException('Directive de cache absente : ' . $directive);
        }
    }

    $templates = glob($root . '/var/templates/*/index.tpl') ?: [];
    $templates[] = $root . '/var/admin/index.tpl';
    $templates[] = $root . '/var/modules/menu/templates/admin.tpl';
    $templates[] = $root . '/var/modules/articles/templates/admin-order.tpl';

    foreach ($templates as $file) {
        $source = (string) file_get_contents($file);
        if (preg_match('/(?:href|src)="(?:var\/[^"?]+\.(?:css|js))"/', $source) === 1) {
            throw new RuntimeException(
                'Ressource CSS ou JavaScript non versionnée dans ' . substr($file, strlen($root) + 1)
            );
        }
    }

    $application = (string) file_get_contents($root . '/src/Core/Application.php');
    foreach (['assetVersion(', "'asset_version'", 'private, no-store', 'public, max-age=300, must-revalidate'] as $marker) {
        if (!str_contains($application, $marker)) {
            throw new RuntimeException('Gestion applicative du cache incomplète : ' . $marker);
        }
    }


    $bootstrap = (string) file_get_contents($root . '/src/Core/Bootstrap.php');
    foreach (['requestNeedsSession(', "['GET', 'HEAD']", "'contact'"] as $marker) {
        if (!str_contains($bootstrap, $marker)) {
            throw new RuntimeException('Démarrage conditionnel des sessions incomplet : ' . $marker);
        }
    }
    $contact = (string) file_get_contents($root . '/var/modules/contact/index.php');
    if (!str_contains($contact, "Cache-Control: private, no-store, max-age=0")) {
        throw new RuntimeException('Le formulaire Contact doit rester hors du cache public.');
    }

    $webp = $root . '/var/templates/univers-guitare/assets/guitar-fretboard-header-0703.webp';
    if (!is_file($webp) || filesize($webp) > 300000) {
        throw new RuntimeException('Image WebP optimisée du thème Univers Guitare absente ou trop lourde.');
    }
    if (is_file($root . '/var/templates/univers-guitare/assets/guitar-fretboard-header.png')) {
        throw new RuntimeException('L’ancienne image PNG lourde est encore distribuée.');
    }
    $guitarCss = (string) file_get_contents($root . '/var/templates/univers-guitare/style.css');
    $guitarTemplate = (string) file_get_contents($root . '/var/templates/univers-guitare/index.tpl');
    if (!str_contains($guitarCss, 'guitar-fretboard-header-0703.webp')
        || !str_contains($guitarTemplate, 'rel="preload"')
        || !str_contains($guitarTemplate, 'guitar-fretboard-header-0703.webp')) {
        throw new RuntimeException('Chargement prioritaire du header WebP Univers Guitare incomplet.');
    }

    echo "Cache statique, sessions publiques, versionnage et image WebP : OK\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Test du cache statique : ÉCHEC - ' . $error->getMessage() . "\n");
    exit(1);
}
