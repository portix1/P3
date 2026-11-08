<?php
declare(strict_types=1);

namespace Portix\Http;

final class Request
{
    /** @param array<string, mixed> $server */
    private function __construct(private readonly array $server)
    {
    }

    public static function fromGlobals(): self
    {
        return new self($_SERVER);
    }

    public function method(): string
    {
        return strtoupper((string) ($this->server['REQUEST_METHOD'] ?? 'GET'));
    }

    public function uri(): string
    {
        return (string) ($this->server['REQUEST_URI'] ?? '/');
    }

    public function path(): string
    {
        $path = parse_url($this->uri(), PHP_URL_PATH);
        return is_string($path) && $path !== '' ? $path : '/';
    }

    public function scriptName(): string
    {
        return (string) ($this->server['SCRIPT_NAME'] ?? '/index.php');
    }

    public function basePath(): string
    {
        $base = str_replace('\\', '/', dirname($this->scriptName()));
        return $base === '/' || $base === '.' ? '' : rtrim($base, '/');
    }

    public function isSecure(): bool
    {
        return !empty($this->server['HTTPS']) && $this->server['HTTPS'] !== 'off';
    }

    /**
     * Schéma public pour les seules URL et redirections. Le proxy peut terminer
     * TLS avant PHP ; ce résultat ne sert jamais à autoriser une opération.
     */
    public function publicScheme(): string
    {
        if ($this->isSecure() || (string) ($this->server['SERVER_PORT'] ?? '') === '443') {
            return 'https';
        }
        // Les reverse proxies sont censés réécrire cet en-tête, et non le relayer
        // tel quel depuis le client. Sans proxy fiable, HTTPS doit être annoncé par
        // le serveur et la redirection de schéma configurée au niveau du vhost.
        $forwarded = strtolower(trim((string) ($this->server['HTTP_X_FORWARDED_PROTO'] ?? '')));
        return $forwarded === 'https' ? 'https' : 'http';
    }

    public function isFrontPage(): bool
    {
        $path = $this->path() === '/' ? '' : rtrim($this->path(), '/');
        return $path === $this->basePath() || $path === $this->basePath() . '/index.php';
    }

    public function isLegacyHome(): bool
    {
        return strcasecmp($this->routeName(), 'accueil') === 0;
    }

    public function isSitemap(): bool
    {
        return strcasecmp($this->path(), $this->basePath() . '/sitemap.xml') === 0;
    }

    public function isRobotsFile(): bool
    {
        return strcasecmp($this->path(), $this->basePath() . '/robots.txt') === 0;
    }

    public function publicHost(): string
    {
        $host = trim((string) ($this->server['HTTP_HOST'] ?? ''));
        if (preg_match('/^(?:\[[0-9A-Fa-f:.]+\]|[A-Za-z0-9.-]+)(?::[1-9][0-9]{0,4})?$/', $host) === 1) {
            return $host;
        }
        $serverName = trim((string) ($this->server['SERVER_NAME'] ?? 'localhost'));
        return preg_match('/^(?:\[[0-9A-Fa-f:.]+\]|[A-Za-z0-9.-]+)$/', $serverName) === 1
            ? $serverName
            : 'localhost';
    }

    public function routeName(): string
    {
        $path = $this->path();
        $basePath = $this->basePath();

        if ($basePath !== '') {
            if ($path === $basePath || $path === $basePath . '/') {
                return '';
            }
            if (str_starts_with($path, $basePath . '/')) {
                $path = substr($path, strlen($basePath));
            }
        }

        $path = ltrim($path, '/');
        if ($path === '' || strcasecmp($path, 'index.php') === 0) {
            return '';
        }

        if (str_starts_with(strtolower($path), 'index.php/')) {
            $path = substr($path, strlen('index.php/'));
        }

        $route = basename($path);
        $route = preg_replace('/\.html$/i', '', $route);
        return trim(rawurldecode((string) $route), '/');
    }

    /** @return list<string> */
    public function routeArguments(string $defaultPage, array $modulePrefixes = []): array
    {
        $route = $this->routeName();
        if ($route === '' || $route === 'index.php') {
            $homeRoute = self::normalizeHomePageTarget($defaultPage);
            return [$homeRoute ?? 'news'];
        }

        if (strcasecmp($route, 'accueil') === 0) {
            $route = self::normalizeHomePageTarget($defaultPage) ?? 'news';
        }

        $segments = array_values(array_filter(
            explode('-', $route),
            static fn (string $value): bool => $value !== ''
        ));
        $prefix = (string) ($segments[0] ?? '');
        $knownPrefixes = array_map('strtolower', $modulePrefixes);
        if (strcasecmp($prefix, 'Admin') === 0
            || (in_array(strtolower($prefix), $knownPrefixes, true)
                && strtolower($prefix) !== 'news')
            || preg_match('/^news(?:-rss|-page-[1-9][0-9]*|-preview-[a-f0-9]{32})?$/i', $route) === 1) {
            return $segments;
        }

        return [$route];
    }

    public static function normalizeHomePageTarget(string $value): ?string
    {
        $value = trim($value);
        if ($value === '' || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
            return null;
        }
        $parts = parse_url($value);
        if (!is_array($parts)
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])) {
            return null;
        }
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if ($scheme !== '' && !in_array($scheme, ['http', 'https'], true)) {
            return null;
        }
        $path = rawurldecode((string) ($parts['path'] ?? ''));
        $route = basename(str_replace('\\', '/', $path));
        $route = preg_replace('/\.html$/i', '', $route) ?? '';
        $route = strtolower(trim($route));
        if (preg_match('/^[a-z0-9](?:[a-z0-9_-]{0,199})$/', $route) !== 1
            || in_array($route, ['accueil', 'admin', 'disconnect', 'index', 'install', 'login', 'personnal', 'forgot-password', 'reset-password'], true)) {
            return null;
        }
        return $route;
    }
}
