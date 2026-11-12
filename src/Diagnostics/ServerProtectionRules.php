<?php
declare(strict_types=1);

namespace Portix\Diagnostics;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Static inspection of distributed Apache/IIS access rules.
 * A successful inspection does not replace HTTP testing on the deployed host.
 */
final class ServerProtectionRules
{
    /** @return array{apache:bool,iis:bool} */
    public static function inspect(string $apache, string $iis): array
    {
        return ['apache' => self::apache($apache), 'iis' => self::iis($iis)];
    }

    private static function apache(string $source): bool
    {
        if ($source === '' || str_contains($source, '\n')) {
            return false;
        }
        $rules = [];
        foreach (preg_split('/\r\n|\r|\n/', $source) ?: [] as $line) {
            // A commented RewriteRule is never considered active.
            if (preg_match('/^\s*RewriteRule\s+(\S+)\s+(\S+)\s+\[([^\]]+)\]\s*$/i', $line, $matches) !== 1) {
                continue;
            }
            $rules[] = [
                $matches[1], $matches[2],
                array_map('strtoupper', array_map('trim', explode(',', $matches[3]))),
            ];
        }
        foreach (self::privatePaths() as $path) {
            if (self::apacheAction($rules, $path) !== 'deny') {
                return false;
            }
        }
        foreach (['index.php', 'install.php', 'var/modules/download/download.php'] as $path) {
            if (self::apacheAction($rules, $path) !== 'allow') {
                return false;
            }
        }
        foreach (self::unknownPhpPaths() as $path) {
            if (self::apacheAction($rules, $path) !== 'rewrite') {
                return false;
            }
        }
        return true;
    }

    /** @param list<array{string,string,list<string>}> $rules */
    private static function apacheAction(array $rules, string $path): string
    {
        foreach ($rules as [$pattern, $target, $flags]) {
            if (@preg_match('~' . str_replace('~', '\~', $pattern) . '~i', $path) !== 1) {
                continue;
            }
            if ($target === '-' && in_array('F', $flags, true) && in_array('L', $flags, true)) {
                return 'deny';
            }
            if ($target === '-' && in_array('L', $flags, true)) {
                return 'allow';
            }
            if ($target === 'index.php' && in_array('L', $flags, true) && in_array('QSA', $flags, true)) {
                return 'rewrite';
            }
            return 'other';
        }
        return 'missing';
    }

    private static function iis(string $source): bool
    {
        if ($source === '') {
            return false;
        }
        $doc = new DOMDocument();
        if (!@$doc->loadXML($source, LIBXML_NONET)) {
            return false;
        }
        $xpath = new DOMXPath($doc);
        $rules = [];
        foreach ($xpath->query('/configuration/system.webServer/rewrite/rules/rule') ?: [] as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }
            $match = $xpath->query('./match', $node)->item(0);
            $action = $xpath->query('./action', $node)->item(0);
            if ($match instanceof DOMElement && $action instanceof DOMElement) {
                $rules[] = [
                    $match->getAttribute('url'), $action->getAttribute('type'),
                    $action->getAttribute('url'), $action->getAttribute('statusCode'),
                ];
            }
        }
        foreach (self::privatePaths() as $path) {
            if (self::iisAction($rules, $path) !== 'deny') {
                return false;
            }
        }
        foreach (['index.php', 'install.php', 'var/modules/download/download.php'] as $path) {
            if (self::iisAction($rules, $path) !== 'missing') {
                return false;
            }
        }
        foreach (self::unknownPhpPaths() as $path) {
            if (self::iisAction($rules, $path) !== 'rewrite') {
                return false;
            }
        }
        return true;
    }

    /** @param list<array{string,string,string,string}> $rules */
    private static function iisAction(array $rules, string $path): string
    {
        foreach ($rules as [$pattern, $type, $target, $status]) {
            if ($pattern === '' || @preg_match('~' . str_replace('~', '\~', $pattern) . '~i', $path) !== 1) {
                continue;
            }
            if ($type === 'CustomResponse' && (int) $status >= 400) {
                return 'deny';
            }
            if ($type === 'Rewrite' && $target === 'index.php') {
                return 'rewrite';
            }
            return 'other';
        }
        return 'missing';
    }

    /** @return list<string> */
    private static function privatePaths(): array
    {
        return [
            'bootstrap/app.php', 'src/Diagnostics/DiagnosticRunner.php',
            'includes/config.php', 'database/site.sqlite', 'sessions/session.dat',
            'tmp/cache.dat', 'tools/smoke_test_security.php',
            'download/private.zip', 'patches/internal.php',
            'var/sys/config.json', 'var/private/secrets.dat',
            'var/modules/home/database/schema.csv', 'var/modules/home/tests/example.php',
        ];
    }

    /** @return list<string> */
    private static function unknownPhpPaths(): array
    {
        return [
            'unlisted.php', 'unlisted.phtml', 'unlisted.phar',
            'unlisted.php8', 'var/modules/home/config.php',
        ];
    }
}
