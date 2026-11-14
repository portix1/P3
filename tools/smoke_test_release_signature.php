<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit(1); }
require dirname(__DIR__) . '/bootstrap/autoload.php';

use Portix\Maintenance\ReleaseSignature;

function signatureRefused(callable $operation): void
{
    try { $operation(); } catch (RuntimeException) { return; }
    throw new RuntimeException('Une signature ou une clé non autorisée a été acceptée.');
}

$key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 3072]);
if ($key === false) { throw new RuntimeException('Clé de test indisponible'); }
$public = openssl_pkey_get_details($key)['key'];
$hash = hash('sha256', 'fixture archive');
$message = $hash . "  P3-0.9.3.zip\n";
if (!openssl_sign($message, $signature, $key, OPENSSL_ALGO_SHA256)) {
    throw new RuntimeException('Signature de test indisponible');
}
ReleaseSignature::verify($public, '0.9.3', $hash, $signature);
signatureRefused(static fn() => ReleaseSignature::verify($public, '0.9.4', $hash, $signature));
signatureRefused(static fn() => ReleaseSignature::verify($public, '0.9.3', str_repeat('0', 64), $signature));
signatureRefused(static fn() => ReleaseSignature::verify($public, '0.9.3', $hash, ''));
signatureRefused(static fn() => ReleaseSignature::verify('not a key', '0.9.3', $hash, $signature));
signatureRefused(static fn() => ReleaseSignature::verify($public, '0.9.3', $hash, str_repeat('x', strlen($signature))));
$root = sys_get_temp_dir() . '/p3-signature-' . bin2hex(random_bytes(8));
mkdir($root . '/var/sys', 0700, true);
$path = $root . '/var/sys/core-release-public.pem';
$bundled = $root . '/src/Maintenance/core-release-public.pem';
mkdir(dirname($bundled), 0700, true);
$official = file_get_contents(dirname(__DIR__) . '/src/Maintenance/core-release-public.pem');
try {
    signatureRefused(static fn() => ReleaseSignature::trustedKey($root));
    file_put_contents($bundled, $public);
    signatureRefused(static fn() => ReleaseSignature::trustedKey($root));
    file_put_contents($bundled, $official);
    if (ReleaseSignature::trustedKey($root) !== $official) { throw new RuntimeException('Clé officielle non chargée'); }
    file_put_contents($path, 'invalid local key');
    signatureRefused(static fn() => ReleaseSignature::trustedKey($root));
    file_put_contents($path, $public);
    if (ReleaseSignature::trustedKey($root) !== $public) { throw new RuntimeException('Clé modifiée'); }
    unlink($path);
    symlink(__FILE__, $path);
    signatureRefused(static fn() => ReleaseSignature::trustedKey($root));
} finally {
    if (is_link($path) || is_file($path)) { unlink($path); }
    if (is_file($bundled)) { unlink($bundled); }
    rmdir(dirname($bundled));
    rmdir($root . '/src');
    rmdir($root . '/var/sys');
    rmdir($root . '/var');
    rmdir($root);
}
echo "Signatures P3 : vérification, falsification, changement de version et clé absente : OK\n";
