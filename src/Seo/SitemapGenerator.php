<?php
declare(strict_types=1);

namespace Portix\Seo;

use PDO;
use Portix\Module\ModuleManifest;
use Throwable;

final class SitemapGenerator
{
    /** @var array<string, string|null> */
    private array $entries = [];
    private string $homeRoute = '';

    public function __construct(
        private readonly string $root,
        private readonly PDO $pdo
    ) {
    }

    /**
     * @param list<array<string, mixed>> $modules
     * @param list<array<string, mixed>> $menuItems
     */
    public function render(string $baseUrl, array $modules, array $menuItems, string $defaultPage = ''): string
    {
        $this->entries = [];
        $baseUrl = rtrim($baseUrl, '/');
        $normalizedHome = \Portix\Http\Request::normalizeHomePageTarget($defaultPage);
        $this->homeRoute = strtolower((string) ($normalizedHome ?? ''));
        $this->add('/', null, $baseUrl);

        foreach ($menuItems as $item) {
            $this->add((string) ($item['url'] ?? ''), null, $baseUrl);
        }

        foreach ($modules as $module) {
            if (empty($module['enabled'])) {
                continue;
            }
            $modulePath = basename((string) ($module['path'] ?? ''));
            $manifestFile = $this->root . '/var/modules/' . $modulePath . '/desc.xml';
            if (!is_file($manifestFile) || is_link($manifestFile)) {
                continue;
            }
            try {
                $manifest = ModuleManifest::fromXml($manifestFile, $modulePath);
                $providerName = (string) $manifest['public_sitemap'];
                if ($providerName === '') {
                    continue;
                }
                $providerFile = $this->root . '/var/modules/' . $modulePath . '/' . $providerName;
                if (!is_file($providerFile) || is_link($providerFile)) {
                    continue;
                }
                $provider = require $providerFile;
                if (!is_callable($provider)) {
                    continue;
                }
                $provided = $provider($this->pdo);
                if (!is_array($provided)) {
                    continue;
                }
                foreach ($provided as $entry) {
                    if (is_array($entry)) {
                        $this->add(
                            (string) ($entry['path'] ?? ''),
                            isset($entry['lastmod']) ? (string) $entry['lastmod'] : null,
                            $baseUrl
                        );
                    }
                }
            } catch (Throwable $error) {
                error_log('Contribution au sitemap ignorée (' . $modulePath . ') : ' . $error->getMessage());
            }
        }

        ksort($this->entries, SORT_STRING);
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xml .= "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
        foreach ($this->entries as $url => $lastModified) {
            $xml .= '  <url><loc>' . self::xml($url) . '</loc>';
            if ($lastModified !== null) {
                $xml .= '<lastmod>' . self::xml($lastModified) . '</lastmod>';
            }
            $xml .= "</url>\n";
        }
        return $xml . "</urlset>\n";
    }

    private function add(string $candidate, ?string $lastModified, string $baseUrl): void
    {
        $path = $this->localPublicPath($candidate, $baseUrl);
        if ($path === null) {
            return;
        }
        $url = $baseUrl . '/' . $path;
        $lastModified = self::date($lastModified);
        if (!array_key_exists($url, $this->entries)
            || ($lastModified !== null && ($this->entries[$url] === null || $lastModified > $this->entries[$url]))) {
            $this->entries[$url] = $lastModified;
        }
    }

    private function localPublicPath(string $candidate, string $baseUrl): ?string
    {
        $candidate = trim($candidate);
        if ($candidate === '' || preg_match('/[\x00-\x1F\x7F]/', $candidate) === 1) {
            return null;
        }
        $parts = parse_url($candidate);
        if (!is_array($parts) || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }
        $path = (string) ($parts['path'] ?? '');
        if (isset($parts['scheme']) || isset($parts['host'])) {
            if (!PublicUrl::isInternalAbsolute($candidate, $baseUrl)) {
                return null;
            }
            $baseParts = parse_url($baseUrl);
            $basePath = rtrim((string) ($baseParts['path'] ?? ''), '/');
            if ($basePath !== '') {
                if ($path !== $basePath && !str_starts_with($path, $basePath . '/')) {
                    return null;
                }
                $path = substr($path, strlen($basePath));
            }
        }
        $path = ltrim(rawurldecode($path), '/');
        if ($path === '') {
            return '';
        }
        if (preg_match('/^[A-Za-z0-9_%~-]+(?:-[A-Za-z0-9_.%~-]+)*\.html$/', $path) !== 1) {
            return null;
        }
        $route = strtolower((string) preg_replace('/\.html$/i', '', $path));
        if ($route === 'accueil' || ($this->homeRoute !== '' && $route === $this->homeRoute)) {
            return '';
        }
        foreach (['admin', 'disconnect', 'forgot-password', 'login', 'personnal', 'reset-password'] as $privateRoute) {
            if ($route === $privateRoute || str_starts_with($route, $privateRoute . '-')) {
                return null;
            }
        }
        return $path;
    }

    private static function date(?string $value): ?string
    {
        $value = trim((string) $value);
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $value, $match) !== 1) {
            return null;
        }
        return $match[1];
    }

    private static function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_XML1, 'UTF-8');
    }
}
