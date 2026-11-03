<?php
declare(strict_types=1);

namespace Portix\Seo;

use Portix\Http\Request;

final class PublicUrl
{
    public static function resolve(string $configuredUrl, Request $request): string
    {
        $configuredUrl = self::validAbsoluteBase($configuredUrl);
        if ($configuredUrl !== '') {
            return $configuredUrl;
        }

        return ($request->isSecure() ? 'https://' : 'http://')
            . $request->publicHost()
            . $request->basePath();
    }

    private static function validAbsoluteBase(string $url): string
    {
        $url = rtrim(trim($url), '/');
        $parts = parse_url($url);
        if ($url === ''
            || !is_array($parts)
            || !in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            || trim((string) ($parts['host'] ?? '')) === ''
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])) {
            return '';
        }

        return $url;
    }
}
