<?php
declare(strict_types=1);

namespace Portix\Insights;

final class TrafficSources
{
    public const SEARCH = 'search';
    public const AI = 'ai';
    public const SITE = 'site';
    public const INTERNAL = 'internal';
    public const DIRECT = 'direct';

    /** Assistants d'IA, examinés avant les moteurs (gemini.google.com avant google.). */
    private const AI_HOSTS = '/(^|\.)(chatgpt\.com|chat\.openai\.com|openai\.com|perplexity\.ai'
        . '|copilot\.microsoft\.com|gemini\.google\.com|bard\.google\.com|claude\.ai|chat\.mistral\.ai'
        . '|chat\.deepseek\.com|you\.com|phind\.com|meta\.ai|grok\.com)$/D';

    private const SEARCH_HOSTS = '/(^|\.)(google\.[a-z.]{2,8}|bing\.com|duckduckgo\.com|ecosia\.org|qwant\.com'
        . '|search\.yahoo\.com|yahoo\.[a-z.]{2,8}|yandex\.[a-z.]{2,8}|baidu\.com|startpage\.com'
        . '|search\.brave\.com|lilo\.org|mojeek\.com|search\.lilo\.org|yep\.com|kagi\.com|seznam\.cz)$/D';

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

    /**
     * Range une provenance déjà classée par classify() et, s'il existe,
     * le paramètre utm_source de l'adresse d'entrée.
     */
    public static function category(string $referrer, ?string $utmSource = null): string
    {
        if ($referrer === 'internal') {
            return self::INTERNAL;
        }
        if ($referrer !== 'direct' && $referrer !== '') {
            $host = self::host($referrer);
            if (preg_match(self::AI_HOSTS, $host) === 1) {
                return self::AI;
            }
            if (preg_match(self::SEARCH_HOSTS, $host) === 1) {
                return self::SEARCH;
            }
        }
        if ($utmSource !== null && preg_match(self::AI_HOSTS, self::host($utmSource)) === 1) {
            return self::AI;
        }
        return $referrer === 'direct' || $referrer === '' ? self::DIRECT : self::SITE;
    }

    /** Valeur utm_source conservée, ou null si absente ou invalide. */
    public static function utmSource(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $value = strtolower(trim($value));
        return preg_match('/^[a-z0-9._-]{1,64}$/D', $value) === 1 ? $value : null;
    }

    /** @return list<string> */
    public static function categories(): array
    {
        return [self::SEARCH, self::AI, self::SITE, self::INTERNAL, self::DIRECT];
    }
}
