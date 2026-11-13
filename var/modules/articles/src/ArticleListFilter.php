<?php
declare(strict_types=1);

namespace PortixModule\Articles;

/** Filter only the articles already authorised for the signed-in user. */
final class ArticleListFilter
{
    /** @param list<array<string,mixed>> $rows @return list<array<string,mixed>> */
    public static function apply(array $rows, string $state, string $query): array
    {
        $state = in_array($state, ['draft', 'pending', 'published'], true) ? $state : '';
        $query = trim($query);
        return array_values(array_filter($rows, static function (array $row) use ($state, $query): bool {
            if ($state !== '' && (string) ($row['status'] ?? '') !== $state) {
                return false;
            }
            if ($query === '') {
                return true;
            }
            $text = (string) ($row['title'] ?? '') . ' ' . (string) ($row['category_name'] ?? '') . ' ' . (string) ($row['slug'] ?? '');
            return mb_stripos($text, $query, 0, 'UTF-8') !== false;
        }));
    }
}
