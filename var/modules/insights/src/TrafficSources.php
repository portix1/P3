<?php
declare(strict_types=1);

namespace Portix\Insights;

final class TrafficSources
{
    private static function host(string $value): string
    {
        $host = strtolower((string) parse_url('http://' . $value, PHP_URL_HOST));
        return preg_replace('/^www\./', '', rtrim($host, '.')) ?? $host;
    }

    public static function classify(string $referrer, string $siteHost): string
    {
        if ($referrer === 'internal' || (self::host($referrer) !== '' && self::host($referrer) === self::host($siteHost))) {
            return 'internal';
        }
        return $referrer;
    }
}
