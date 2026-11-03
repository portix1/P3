<?php
declare(strict_types=1);

namespace Portix\Support;

final class Slugger
{
    /**
     * Translittération déterministe des caractères latins courants.
     *
     * iconv dépend de son implémentation et de la plateforme : certaines
     * versions Windows transforment notamment « é » en « 'e ». Cette table
     * traite d’abord les caractères utilisés dans les langues européennes.
     */
    private const LATIN_TRANSLITERATION = [
        'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A',
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
        'Æ' => 'AE', 'æ' => 'ae', 'Ç' => 'C', 'ç' => 'c',
        'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E',
        'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
        'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
        'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
        'Ñ' => 'N', 'ñ' => 'n',
        'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ø' => 'O',
        'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ø' => 'o',
        'Œ' => 'OE', 'œ' => 'oe',
        'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U',
        'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
        'Ý' => 'Y', 'Ÿ' => 'Y', 'ý' => 'y', 'ÿ' => 'y',
        'Š' => 'S', 'š' => 's', 'Ž' => 'Z', 'ž' => 'z',
        'Č' => 'C', 'č' => 'c', 'Ć' => 'C', 'ć' => 'c',
        'Đ' => 'D', 'đ' => 'd', 'Ł' => 'L', 'ł' => 'l',
        'Ğ' => 'G', 'ğ' => 'g', 'İ' => 'I', 'ı' => 'i', 'Ş' => 'S', 'ş' => 's',
        'Ð' => 'D', 'ð' => 'd', 'Þ' => 'TH', 'þ' => 'th', 'ß' => 'ss',
        '’' => "'", '‘' => "'", 'ʼ' => "'",
        '‐' => '-', '‑' => '-', '–' => '-', '—' => '-',
    ];

    public static function fromText(string $value, string $fallback = 'actualite'): string
    {
        $value = html_entity_decode(trim($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = strtr($value, self::LATIN_TRANSLITERATION);
        $value = preg_replace('/\p{Mn}+/u', '', $value) ?? $value;
        $transliterated = function_exists('iconv')
            ? iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value)
            : false;
        if (is_string($transliterated) && $transliterated !== '') {
            $value = $transliterated;
        }
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        $value = trim($value, '-');
        $fallback = preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $fallback) === 1
            ? $fallback
            : 'contenu';
        return $value !== '' ? substr($value, 0, 180) : $fallback;
    }
}
