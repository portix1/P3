<?php
declare(strict_types=1);

namespace Portix\Core;

use Portix\Configuration\SiteConfiguration;
use Portix\Http\Request;
use Portix\Infrastructure\Database;
use RuntimeException;

final class Bootstrap
{
    public static function boot(string $root): Application
    {
        if (PHP_VERSION_ID < 80200) {
            throw new RuntimeException('P3 nécessite PHP 8.2 ou plus récent.');
        }

        $root = rtrim($root, '/\\');
        chdir($root);
        self::loadRuntimeServices($root);

        $configuration = SiteConfiguration::load($root);
        $debug = (bool) $configuration->get('debug', false);
        error_reporting(E_ALL);
        ini_set('display_errors', $debug ? '1' : '0');
        ini_set('log_errors', '1');

        $request = Request::fromGlobals();
        if (self::requestNeedsSession($request)) {
            self::startSession($root, $request);
        }

        $database = new Database($root . '/database/portix2.sqlite');
        return new Application($root, $request, $configuration, $database);
    }

    private static function loadRuntimeServices(string $root): void
    {
        foreach ([
            '/includes/class/security.class.php',
            '/includes/class/settings.class.php',
            '/includes/class/zip.class.php',
        ] as $file) {
            require_once $root . $file;
        }
    }

    private static function requestNeedsSession(Request $request): bool
    {
        if (!in_array($request->method(), ['GET', 'HEAD'], true)) {
            return true;
        }

        $cookieName = session_name();
        if ($cookieName !== '' && isset($_COOKIE[$cookieName]) && (string) $_COOKIE[$cookieName] !== '') {
            return true;
        }

        $route = strtolower($request->routeName());
        foreach (['admin', 'login', 'disconnect', 'personnal', 'forgot-password', 'reset-password', 'contact'] as $prefix) {
            if ($route === $prefix || str_starts_with($route, $prefix . '-')) {
                return true;
            }
        }

        return false;
    }

    private static function startSession(string $root, Request $request): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }
        $directory = $root . '/sessions';
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new RuntimeException('Impossible de créer le dossier de sessions.');
        }
        if (is_writable($directory)) {
            session_save_path($directory);
        }
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $request->isSecure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        if (!session_start()) {
            throw new RuntimeException('Impossible de démarrer la session.');
        }
    }
}
