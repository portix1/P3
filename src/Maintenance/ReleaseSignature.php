<?php
declare(strict_types=1);

namespace Portix\Maintenance;

use RuntimeException;

/**
 * RSA/SHA-256 verification of the exact canonical checksum line.
 * The bundled official key is used on new installations; a local key takes precedence.
 */
final class ReleaseSignature
{
    private const KEY_FILE = 'var/sys/core-release-public.pem';
    private const BUNDLED_KEY = 'src/Maintenance/core-release-public.pem';
    private const BUNDLED_FINGERPRINT = '26aa2418c7795362ef7c258f688f0190707972466deea7631b50f9bb7586cda6';

    public static function trustedKey(string $root): string
    {
        $root = rtrim($root, '/\\');
        foreach (['var', 'var/sys', self::KEY_FILE] as $relative) {
            if (is_link($root . '/' . $relative)) {
                throw new RuntimeException('Clé de confiance : lien symbolique refusé.');
            }
        }
        $path = $root . '/' . self::KEY_FILE;
        $bundled = !file_exists($path);
        if ($bundled) {
            foreach (['src', 'src/Maintenance', self::BUNDLED_KEY] as $relative) {
                if (is_link($root . '/' . $relative)) {
                    throw new RuntimeException('Clé officielle : lien symbolique refusé.');
                }
            }
            $path = $root . '/' . self::BUNDLED_KEY;
        }
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException('Mise à jour refusée : clé publique de confiance indisponible.');
        }
        $pem = file_get_contents($path, false, null, 0, 16385);
        if (!is_string($pem) || strlen($pem) > 16384) {
            throw new RuntimeException('Clé publique de confiance illisible ou trop volumineuse.');
        }
        $key = self::parseKey($pem);
        if ($bundled) {
            $details = openssl_pkey_get_details($key);
            $canonical = is_array($details) ? ($details['key'] ?? '') : '';
            $der = base64_decode((string) preg_replace('/-----[^-]+-----|\s+/', '', $canonical), true);
            if ($der === false || !hash_equals(self::BUNDLED_FINGERPRINT, hash('sha256', $der))) {
                throw new RuntimeException('Mise à jour refusée : empreinte de la clé officielle incorrecte.');
            }
        }
        return $pem;
    }

    private static function parseKey(string $pem): \OpenSSLAsymmetricKey
    {
        if (!function_exists('openssl_verify')) {
            throw new RuntimeException('Mise à jour signée indisponible : extension PHP OpenSSL absente.');
        }
        $key = @openssl_pkey_get_public($pem);
        $details = $key === false ? false : openssl_pkey_get_details($key);
        if ($key === false || !is_array($details) || $details['type'] !== OPENSSL_KEYTYPE_RSA
            || $details['bits'] < 3072 || $details['bits'] > 8192) {
            throw new RuntimeException('Clé publique de confiance invalide : RSA 3072 à 8192 bits requis.');
        }
        return $key;
    }

    public static function verify(string $pem, string $version, string $sha256, string $signature): void
    {
        if (preg_match('/\A[0-9]+\.[0-9]+\.[0-9]+\z/D', $version) !== 1
            || preg_match('/\A[a-f0-9]{64}\z/D', $sha256) !== 1
            || strlen($signature) < 384 || strlen($signature) > 1024) {
            throw new RuntimeException('Signature ou métadonnées de version invalides.');
        }
        $message = $sha256 . '  P3-' . $version . ".zip\n";
        if (openssl_verify($message, $signature, self::parseKey($pem), OPENSSL_ALGO_SHA256) !== 1) {
            throw new RuntimeException('Mise à jour refusée : signature officielle non valide.');
        }
    }
}
