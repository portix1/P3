<?php
declare(strict_types=1);

namespace Portix\Seo;

use Portix\Http\Request;

/** L'adresse publique configurée est l'unique référence pour les URL générées. */
final class PublicUrl
{
    public static function resolve(string $configuredUrl, Request $request): string
    {
        $configuredUrl = self::validAbsoluteBase($configuredUrl);
        if ($configuredUrl !== '') {
            return $configuredUrl;
        }

        return $request->publicScheme() . '://'
            . $request->publicHost()
            . $request->basePath();
    }

    /**
     * Renvoie la destination permanente d'une requête dynamique non canonique.
     * Ne redirige pas sans site_url explicite (protection contre Host injection).
     */
    public static function redirectLocation(string $configuredUrl, Request $request): ?string
    {
        $base = self::validAbsoluteBase($configuredUrl);
        if ($base === '') {
            return null;
        }
        $uri = $request->uri();
        if ($uri === '' || $uri[0] !== '/' || str_starts_with($uri, '//')
            || preg_match('/[\x00-\x1F\x7F]/', $uri) === 1) {
            return null;
        }

        $canonical = parse_url($base);
        $incoming = parse_url('http://' . $request->publicHost());
        if (!is_array($canonical) || !is_array($incoming)) {
            return null;
        }
        // Ne jamais déplacer un site installé dans un sous-répertoire.
        $canonicalBasePath = rtrim((string) ($canonical['path'] ?? ''), '/');
        if ($canonicalBasePath !== $request->basePath()) {
            return null;
        }
        $host = strtolower((string) ($incoming['host'] ?? ''));
        $scheme = $request->publicScheme();
        $targetScheme = strtolower((string) $canonical['scheme']);
        $targetHost = strtolower((string) $canonical['host']);
        $requestPort = (int) ($incoming['port'] ?? self::defaultPort($scheme));
        $canonicalPort = (int) ($canonical['port'] ?? self::defaultPort($targetScheme));
        if ($host === $targetHost && $scheme === $targetScheme && $requestPort === $canonicalPort) {
            return null;
        }

        $authority = $targetHost . (isset($canonical['port']) ? ':' . (int) $canonical['port'] : '');
        return $targetScheme . '://' . $authority . $uri;
    }

    /**
     * Convertit uniquement les liens absolus appartenant au même site
     * (variante www / sans www, HTTP / HTTPS) ; conserve chemins et fragments.
     * Les liens externes restent intacts et aucune donnée enregistrée n'est modifiée.
     */
    public static function canonicalizeInternal(string $url, string $configuredBase): string
    {
        if (!self::isInternalAbsolute($url, $configuredBase)) {
            return $url;
        }
        $base = self::validAbsoluteBase($configuredBase);
        $parts = parse_url($url);
        $target = parse_url($base);
        if (!is_array($parts) || !is_array($target)) {
            return $url;
        }
        $origin = strtolower((string) $target['scheme']) . '://'
            . strtolower((string) $target['host'])
            . (isset($target['port']) ? ':' . (int) $target['port'] : '');
        $path = (string) ($parts['path'] ?? '/');
        return $origin . ($path !== '' ? $path : '/')
            . (isset($parts['query']) ? '?' . $parts['query'] : '')
            . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');
    }

    public static function isInternalAbsolute(string $url, string $configuredBase): bool
    {
        $base = self::validAbsoluteBase($configuredBase);
        if ($base === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }
        $candidate = parse_url($url);
        $target = parse_url($base);
        if (!is_array($candidate) || !is_array($target)
            || !in_array(strtolower((string) ($candidate['scheme'] ?? '')), ['http', 'https'], true)
            || isset($candidate['user']) || isset($candidate['pass'])) {
            return false;
        }
        $candidateHost = strtolower((string) ($candidate['host'] ?? ''));
        $targetHost = strtolower((string) $target['host']);
        $withoutWww = static fn (string $host): string => str_starts_with($host, 'www.') ? substr($host, 4) : $host;
        if ($withoutWww($candidateHost) !== $withoutWww($targetHost)) {
            return false;
        }
        // Les ports non standards ne sont jamais assimilés par erreur.
        $port = (int) ($candidate['port'] ?? self::defaultPort(strtolower((string) $candidate['scheme'])));
        $targetPort = (int) ($target['port'] ?? self::defaultPort(strtolower((string) $target['scheme'])));
        if (($port !== self::defaultPort(strtolower((string) $candidate['scheme']))
                || $targetPort !== self::defaultPort(strtolower((string) $target['scheme'])))
            && $port !== $targetPort) {
            return false;
        }
        $basePath = rtrim((string) ($target['path'] ?? ''), '/');
        $candidatePath = (string) ($candidate['path'] ?? '/');
        return $basePath === '' || $candidatePath === $basePath || str_starts_with($candidatePath, $basePath . '/');
    }

    public static function isValidBase(string $url): bool
    {
        return self::validAbsoluteBase($url) !== '';
    }

    private static function validAbsoluteBase(string $url): string
    {
        $url = rtrim(trim($url), '/');
        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return '';
        }
        $parts = parse_url($url);
        if (!is_array($parts)
            || !in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            || trim((string) ($parts['host'] ?? '')) === ''
            || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['query']) || isset($parts['fragment'])
            || preg_match('/[\x00-\x20\x7F<>"\\\\]/', $url) === 1) {
            return '';
        }
        return $url;
    }

    private static function defaultPort(string $scheme): int
    {
        return $scheme === 'https' ? 443 : 80;
    }
}
