<?php
declare(strict_types=1);

namespace Portix\Installation;

use Throwable;

final class EnvironmentPreflight
{
    /** @var list<string> */
    private const REQUIRED_EXTENSIONS = [
        'pdo',
        'pdo_sqlite',
        'session',
        'dom',
        'gd',
        'iconv',
        'fileinfo',
        'mbstring',
        'zip',
    ];

    /** @var list<string> */
    private const WRITABLE_DIRECTORIES = [
        'database',
        'sessions',
        'tmp',
        'var/sys',
        'var/private',
        'var/private/downloads',
        'var/uploads/images',
    ];

    public function __construct(private readonly string $root)
    {
    }

    /**
     * @return array{
     *     ok: bool,
     *     checks: list<array{label:string, ok:bool, detail:string}>,
     *     directories: list<array{label:string, ok:bool, detail:string}>
     * }
     */
    public function run(): array
    {
        $checks = [];
        $phpOk = PHP_VERSION_ID >= 80200;
        $checks[] = [
            'label' => 'PHP 8.2 ou plus récent',
            'ok' => $phpOk,
            'detail' => 'Version détectée : ' . PHP_VERSION,
        ];

        foreach (self::REQUIRED_EXTENSIONS as $extension) {
            $loaded = extension_loaded($extension);
            $checks[] = [
                'label' => 'Extension PHP ' . $extension,
                'ok' => $loaded,
                'detail' => $loaded ? 'Disponible' : 'Extension manquante',
            ];
        }

        $webp = function_exists('imagewebp');
        $checks[] = [
            'label' => 'Support WebP de PHP GD',
            'ok' => $webp,
            'detail' => $webp ? 'Disponible' : 'Fonction imagewebp() absente',
        ];

        $directories = [];
        foreach (self::WRITABLE_DIRECTORIES as $relative) {
            $directories[] = $this->prepareDirectory($relative);
        }

        $ok = true;
        foreach (array_merge($checks, $directories) as $check) {
            if (!$check['ok']) {
                $ok = false;
                break;
            }
        }

        return [
            'ok' => $ok,
            'checks' => $checks,
            'directories' => $directories,
        ];
    }

    /** @return list<string> */
    public static function writableDirectories(): array
    {
        return self::WRITABLE_DIRECTORIES;
    }

    /** @return array{label:string, ok:bool, detail:string} */
    private function prepareDirectory(string $relative): array
    {
        $directory = $this->root . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $created = false;
        $chmodAttempted = false;

        try {
            if (!is_dir($directory)) {
                if (!@mkdir($directory, 0770, true) && !is_dir($directory)) {
                    return [
                        'label' => $relative . '/',
                        'ok' => false,
                        'detail' => 'Impossible de créer le dossier.',
                    ];
                }
                $created = true;
            }

            if (DIRECTORY_SEPARATOR !== '\\') {
                if ($created) {
                    @chmod($directory, 0770);
                    $chmodAttempted = true;
                } elseif (!is_writable($directory)) {
                    @chmod($directory, 0770);
                    $chmodAttempted = true;
                }
            }

            clearstatcache(true, $directory);
            $writeTest = $this->testWriteAndDelete($directory);
            if (!$writeTest['ok']) {
                return [
                    'label' => $relative . '/',
                    'ok' => false,
                    'detail' => $writeTest['detail']
                        . ($chmodAttempted ? ' Une correction des permissions a été tentée.' : ''),
                ];
            }

            $detailParts = [];
            $detailParts[] = $created ? 'Dossier créé' : 'Dossier présent';
            if ($chmodAttempted && DIRECTORY_SEPARATOR !== '\\') {
                $detailParts[] = 'permissions préparées';
            }
            $detailParts[] = 'écriture et suppression vérifiées';

            return [
                'label' => $relative . '/',
                'ok' => true,
                'detail' => implode(', ', $detailParts) . '.',
            ];
        } catch (Throwable $error) {
            return [
                'label' => $relative . '/',
                'ok' => false,
                'detail' => 'Échec du contrôle : ' . $error->getMessage(),
            ];
        }
    }

    /** @return array{ok:bool, detail:string} */
    private function testWriteAndDelete(string $directory): array
    {
        $probe = $directory . DIRECTORY_SEPARATOR . '.portix-write-test-' . bin2hex(random_bytes(6));
        $payload = 'portix-' . bin2hex(random_bytes(8));

        $written = @file_put_contents($probe, $payload, LOCK_EX);
        if ($written === false) {
            return [
                'ok' => false,
                'detail' => 'PHP ne peut pas écrire dans ce dossier.',
            ];
        }

        $read = @file_get_contents($probe);
        if ($read !== $payload) {
            @unlink($probe);
            return [
                'ok' => false,
                'detail' => 'Le fichier de contrôle a été écrit mais ne peut pas être relu correctement.',
            ];
        }

        if (!@unlink($probe)) {
            return [
                'ok' => false,
                'detail' => 'PHP peut écrire mais ne peut pas supprimer un fichier dans ce dossier.',
            ];
        }

        return ['ok' => true, 'detail' => 'OK'];
    }
}
