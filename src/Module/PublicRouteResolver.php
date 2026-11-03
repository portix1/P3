<?php
declare(strict_types=1);

namespace Portix\Module;

use PDO;
use Throwable;

final class PublicRouteResolver
{
    public function __construct(
        private readonly string $root,
        private readonly PDO $pdo
    ) {
    }

    /**
     * @param list<array<string, mixed>> $modules
     * @return list<string>|null
     */
    public function resolve(string $slug, array $modules): ?array
    {
        if (preg_match('/^[a-z0-9][a-z0-9-]{0,199}$/', $slug) !== 1) {
            return null;
        }

        foreach ($modules as $module) {
            if (empty($module['enabled']) || (string) ($module['type'] ?? '') !== 'module') {
                continue;
            }
            $modulePath = basename((string) ($module['path'] ?? ''));
            $manifestFile = $this->root . '/var/modules/' . $modulePath . '/desc.xml';
            if (!is_file($manifestFile) || is_link($manifestFile)) {
                continue;
            }

            try {
                $manifest = ModuleManifest::fromXml($manifestFile, $modulePath);
                $resolverName = (string) $manifest['public_route_resolver'];
                if ($resolverName === '') {
                    continue;
                }
                $resolverFile = $this->root . '/var/modules/' . $modulePath . '/' . $resolverName;
                if (!is_file($resolverFile) || is_link($resolverFile)) {
                    continue;
                }
                $resolver = require $resolverFile;
                if (!is_callable($resolver)) {
                    throw new \RuntimeException('modules_public_route_resolver_invalid');
                }
                $route = $resolver($this->pdo, $slug);
                if ($route === null) {
                    continue;
                }
                if (!$this->isValidRoute($route, (string) $manifest['prefix'])) {
                    throw new \RuntimeException('modules_public_route_invalid');
                }
                return array_values($route);
            } catch (Throwable $error) {
                error_log((string) $error);
            }
        }
        return null;
    }

    private function isValidRoute(mixed $route, string $prefix): bool
    {
        if (!is_array($route) || $route === [] || !array_is_list($route)) {
            return false;
        }
        foreach ($route as $segment) {
            if (!is_string($segment)
                || preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,199}$/', $segment) !== 1) {
                return false;
            }
        }
        return strcasecmp($route[0], $prefix) === 0;
    }
}
