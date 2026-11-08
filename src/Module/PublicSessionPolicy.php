<?php
declare(strict_types=1);
namespace Portix\Module;

use Portix\Http\Request;

/** Opt-in public POST actions can avoid creating anonymous sessions.
 * Existing session cookies are always handled normally by Bootstrap.
 * This policy does not dispatch routes, enable modules, or grant permissions.
 */
final class PublicSessionPolicy
{
    public static function allows(Request $request, string $root): bool
    {
        if ($request->method() !== 'POST' || preg_match('/^([a-zA-Z0-9_]+)-([a-z][a-z0-9_]{0,31})$/D', $request->routeName(), $match) !== 1) { return false; }
        foreach (glob($root . '/var/modules/*/desc.xml') ?: [] as $file) {
            $directory = dirname($file);
            if (is_link($directory) || is_link($file) || is_file($directory . '/AUTH')) { continue; }
            try {
                $manifest = ModuleManifest::fromXml($file, basename($directory));
                if ($manifest['type'] === 'module' && $manifest['prefix'] === $match[1]) {
                    return in_array($match[2], $manifest['sessionless_post_actions'], true);
                }
            } catch (\Throwable) { continue; }
        }
        return false;
    }
}
