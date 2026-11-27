<?php
declare(strict_types=1);

namespace Portix\Insights;

/**
 * Classe un User-Agent sans le conserver.
 *
 * Seule la catégorie est enregistrée. Les listes sont volontairement
 * explicites : elles sont à entretenir au fil des versions.
 */
final class AgentClassifier
{
    public const BROWSER = 'browser';
    public const SEARCH = 'search';
    public const AI_TRAINING = 'ai-training';
    public const AI_USER = 'ai-user';
    public const OTHER = 'other-robot';

    /** @var array<string, string> catégorie => motif, dans l'ordre d'examen */
    private const PATTERNS = [
        // IA consultant une page pour répondre à un utilisateur.
        self::AI_USER => '/chatgpt-user|oai-searchbot|claude-user|claude-searchbot|perplexity-user'
            . '|mistralai-user|duckassistbot|meta-externalfetcher|youbot/i',
        // Collecte de textes pour entraîner ou alimenter des modèles d'IA.
        self::AI_TRAINING => '/gptbot|claudebot|anthropic-ai|claude-web|ccbot|perplexitybot|google-extended'
            . '|bytespider|amazonbot|applebot-extended|meta-externalagent|cohere-ai|diffbot|ai2bot'
            . '|omgilibot|timpibot|imagesiftbot|petalbot|mistralai/i',
        // Robots des moteurs de recherche.
        self::SEARCH => '/googlebot|google-inspectiontool|storebot-google|adsbot-google|bingbot|bingpreview'
            . '|duckduckbot|yandexbot|baiduspider|qwantbot|qwantify|applebot|seznambot|slurp|mojeekbot'
            . '|ecosiabot|yeti\//i',
        // Mentions d'assistants non répertoriées plus haut : jamais un lecteur.
        'ai-user-fallback' => '/anthropic|claude|chatgpt|openai|perplexity/i',
        // Tout autre robot ou outil automatique.
        self::OTHER => '/bot|crawl|spider|slurp|facebookexternalhit|headless|monitor|uptime|ghost-rider'
            . '|lighthouse|pagespeed|curl\/|wget\/|python-requests|go-http-client|httpclient|scrapy/i',
    ];

    public static function classify(string $agent): string
    {
        if (trim($agent) === '') {
            return self::BROWSER;
        }
        foreach (self::PATTERNS as $category => $pattern) {
            if (preg_match($pattern, $agent) === 1) {
                return $category === 'ai-user-fallback' ? self::AI_USER : $category;
            }
        }
        return self::BROWSER;
    }

    /** @return list<string> */
    public static function robotCategories(): array
    {
        return [self::SEARCH, self::AI_TRAINING, self::AI_USER, self::OTHER];
    }
}
