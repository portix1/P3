<?php
declare(strict_types=1);

namespace Portix\Module;

use DOMDocument;
use RuntimeException;

final class ModuleManifest
{
    /** @return array{name:string,prefix:string,type:string,priority:int,default_language:string,enabled:bool,path:string,admin_access:string,schema_format:string,version:string,public_navigation:string,public_route_resolver:string,public_sitemap:string} */
    public static function fromXml(string $file, string $expectedPath): array
    {
        $contents = file_get_contents($file);
        if ($contents === false) {
            throw new RuntimeException('modules_manifest_read_failed');
        }

        $previous = libxml_use_internal_errors(true);
        try {
            $document = new DOMDocument();
            if (!$document->loadXML($contents, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
                throw new RuntimeException('modules_manifest_invalid_xml');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $value = static function (string $name) use ($document): string {
            $nodes = $document->getElementsByTagName($name);
            return $nodes->length > 0 ? trim((string) $nodes->item(0)?->textContent) : '';
        };

        $manifest = [
            'name' => $value('Name'),
            'prefix' => $value('Prefix'),
            'type' => $value('Type'),
            'priority' => (int) $value('Priority'),
            'default_language' => $value('DefLang') ?: 'fr',
            'enabled' => in_array(strtolower($value('Enabled')), ['1', 'true', 'yes'], true),
            'path' => $value('Path'),
            'admin_access' => strtolower($value('AdminAccess') ?: 'admin'),
            'schema_format' => strtolower($value('SchemaFormat') ?: 'none'),
            'version' => $value('Version'),
            'public_navigation' => $value('PublicNavigation'),
            'public_route_resolver' => $value('PublicRouteResolver'),
            'public_sitemap' => $value('PublicSitemap'),
        ];

        if ($manifest['name'] === ''
            || preg_match('/^[A-Za-z0-9_]+$/', $manifest['prefix']) !== 1
            || !in_array($manifest['type'], ['module', 'plugin'], true)
            || preg_match('/^[A-Za-z0-9_-]+$/', $manifest['default_language']) !== 1
            || !in_array($manifest['admin_access'], ['none', 'editor', 'admin'], true)
            || !in_array($manifest['schema_format'], ['none', 'desc-csv'], true)
            || ($manifest['version'] !== '' && preg_match('/^[0-9]+(?:\.[0-9]+){0,3}(?:[-+][A-Za-z0-9.-]+)?$/', $manifest['version']) !== 1)
            || ($manifest['public_navigation'] !== ''
                && preg_match('/^[A-Za-z0-9_-]+\.php$/', $manifest['public_navigation']) !== 1)
            || ($manifest['public_route_resolver'] !== ''
                && preg_match('/^[A-Za-z0-9_-]+\.php$/', $manifest['public_route_resolver']) !== 1)
            || ($manifest['public_sitemap'] !== ''
                && preg_match('/^[A-Za-z0-9_-]+\.php$/', $manifest['public_sitemap']) !== 1)
            || preg_match('/^[A-Za-z0-9][A-Za-z0-9_.-]{0,62}$/', $manifest['path']) !== 1
            || $manifest['path'] !== $expectedPath) {
            throw new RuntimeException('modules_manifest_invalid');
        }
        $manifest['priority'] = max(0, $manifest['priority']);

        return $manifest;
    }
}
