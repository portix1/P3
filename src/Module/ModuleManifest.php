<?php
declare(strict_types=1);

namespace Portix\Module;

use DOMDocument;
use RuntimeException;

final class ModuleManifest
{
    public const EXTENSION_CONTRACT = 1;
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
            'minimum_p3' => $value('MinimumP3'),
            'admin_navigation' => self::navigation($document),
            'sessionless_post_actions' => self::sessionlessActions($value('SessionlessPostActions')),
        ];

        if ($manifest['name'] === ''
            || preg_match('/^[A-Za-z0-9_]+$/', $manifest['prefix']) !== 1
            || !in_array($manifest['type'], ['module', 'plugin'], true)
            || preg_match('/^[A-Za-z0-9_-]+$/', $manifest['default_language']) !== 1
            || !in_array($manifest['admin_access'], ['none', 'editor', 'admin'], true)
            || !in_array($manifest['schema_format'], ['none', 'desc-csv'], true)
            || ($manifest['version'] !== '' && preg_match('/^[0-9]+(?:\.[0-9]+){0,3}(?:[-+][A-Za-z0-9.-]+)?$/', $manifest['version']) !== 1)
            || ($manifest['minimum_p3'] !== '' && preg_match('/^[0-9]+\.[0-9]+\.[0-9]+(?:-[A-Za-z0-9.-]+)?$/D', $manifest['minimum_p3']) !== 1)
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
        // A legacy Contact package must not reopen access to private messages.
        if ($manifest['prefix'] === 'contact' && $manifest['admin_access'] === 'editor') {
            $manifest['admin_access'] = 'admin';
        }
        $manifest['priority'] = max(0, $manifest['priority']);

        return $manifest;
    }
    /** Version requirements are checked before installation mutates files or tables. */
    public static function assertCompatible(array $manifest, string $version): void
    {
        if (($manifest['minimum_p3'] ?? '') !== '' && version_compare($version, $manifest['minimum_p3'], '<')) {
            throw new RuntimeException('P3 >= ' . $manifest['minimum_p3'] . ' requis pour ce module.');
        }
    }

    /** Prevent an accidental downgrade or replacement by a different module. */
    public static function assertUpgrade(array $candidate, array $installed): void
    {
        foreach (['prefix', 'path', 'type'] as $identity) {
            if (($candidate[$identity] ?? '') !== ($installed[$identity] ?? '')) {
                throw new RuntimeException('modules_identity_mismatch');
            }
        }
        $previous = (string) ($installed['version'] ?? '');
        $next = (string) ($candidate['version'] ?? '');
        if ($previous !== '' && ($next === '' || version_compare($next, $previous, '<'))) {
            throw new RuntimeException('modules_downgrade_denied');
        }
        // An equal version is allowed for a repair/reinstall of the same module.
    }

    private static function sessionlessActions(string $value): array
    {
        if ($value === '') { return []; }
        $actions = array_map('trim', explode(',', $value));
        if (count($actions) > 8) { throw new RuntimeException('modules_manifest_invalid'); }
        foreach ($actions as $action) {
            if (preg_match('/^[a-z][a-z0-9_]{0,31}$/D', $action) !== 1) { throw new RuntimeException('modules_manifest_invalid'); }
        }
        return array_values(array_unique($actions));
    }

    private static function navigation(DOMDocument $document): array
    {
        $xpath = new \DOMXPath($document);
        $nodes = $xpath->query('/modules/AdminNavigation/Item');
        if ($nodes === false || $nodes->length > 20) { throw new RuntimeException('modules_manifest_invalid'); }
        $items = []; $seen = [];
        foreach ($nodes as $node) {
            $item = [];
            foreach (['area', 'action', 'role', 'icon', 'label'] as $attribute) { $item[$attribute] = $node->getAttribute($attribute); }
            if (!in_array($item['area'], ['writing', 'management', 'configuration'], true)
                || !in_array($item['role'], ['editor', 'admin'], true)
                || ($item['role'] === 'editor' && $item['area'] !== 'writing')
                || preg_match('/^(?:[a-z][a-z0-9_]*(?:-[a-z0-9_]+)*)?$/D', $item['action']) !== 1
                || strlen($item['action']) > 80
                || preg_match('/^[a-z][a-z0-9-]{0,40}$/D', $item['icon']) !== 1
                || preg_match('/^[a-z][a-z0-9_]{0,80}$/D', $item['label']) !== 1
                || isset($seen[$item['action']])) { throw new RuntimeException('modules_manifest_invalid'); }
            $seen[$item['action']] = true;
            $items[] = $item;
        }
        return $items;
    }
}
