<?php
declare(strict_types=1);

namespace Portix\Core;

use Portix\Configuration\SiteConfiguration;
use Portix\Http\Request;
use Portix\Infrastructure\Database;
use RuntimeException;

final class Bootstrap
{
    public static function boot(string $root, ?string $installationDatabase = null): Application
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
        if (self::requestNeedsSession($request, $root)) {
            self::startSession($root, $request, $configuration);
        }

        if ($installationDatabase !== null && $configuration->isInstalled()) {
            throw new RuntimeException('P3 est déjà configuré.');
        }
        $relativePath = $installationDatabase === null
            ? $configuration->databasePath()
            : SiteConfiguration::databaseRelativePath($installationDatabase);
        $database = new Database($root . '/' . $relativePath);
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

    private static function requestNeedsSession(Request $request, string $root): bool
    {
        // Only an explicitly declared public action may skip anonymous session creation.
        if (empty($_COOKIE[session_name()]) && \Portix\Module\PublicSessionPolicy::allows($request, $root)) {
            return false;
        }
        if (!in_array($request->method(), ['GET', 'HEAD'], true)) {
            return true;
        }

        $cookieName = session_name();
        if ($cookieName !== '' && isset($_COOKIE[$cookieName]) && (string) $_COOKIE[$cookieName] !== '') {
            return true;
        }

        if (basename($request->path()) === 'install.php') {
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

    private static function startSession(string $root, Request $request, SiteConfiguration $configuration): void
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
        // Refuse client-supplied session identifiers not already created by PHP.
        if (ini_set('session.use_strict_mode', '1') === false || ini_set('session.use_only_cookies', '1') === false) {
            throw new RuntimeException('Impossible de sécuriser les identifiants de session.');
        }
        if (!session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $request->secureSessionCookie((string) $configuration->get('site_url', '')),
            'httponly' => true,
            'samesite' => 'Lax',
        ])) {
            throw new RuntimeException('Impossible de sécuriser le cookie de session.');
        }
        if (!session_start()) {
            throw new RuntimeException('Impossible de démarrer la session.');
        }
    }
}
