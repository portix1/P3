<?php
declare(strict_types=1);

namespace Portix\Insights;

/** Load module labels independently of the engine's global language export. */
final class AdminLabels
{
    public static function forLanguage(string $language): array
    {
        $labels = self::load('fr');
        return $language === 'en' ? array_replace($labels, self::load('en')) : $labels;
    }

    private static function load(string $language): array
    {
        return require __DIR__ . '/labels/' . $language . '.php';
    }
}
