<?php
declare(strict_types=1);

namespace PortixModule\Articles;

final class ArticleListPagination
{
    /**
     * @param list<array<string, mixed>> $rows
     * @return array{rows:list<array<string, mixed>>,page:int,pages:int,total:int,from:int,to:int}
     */
    public static function paginate(array $rows, int $requestedPage, int $perPage = 25): array
    {
        $perPage = max(1, min(100, $perPage));
        $total = count($rows);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($requestedPage, $pages));
        $offset = ($page - 1) * $perPage;
        $items = array_slice($rows, $offset, $perPage);
        return [
            'rows' => $items,
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
            'from' => $total === 0 ? 0 : $offset + 1,
            'to' => $offset + count($items),
        ];
    }

    public static function pageUrl(int $page, string $state, string $query): string
    {
        $params = ['page' => $page];
        if ($state !== '') { $params['state'] = $state; }
        if ($query !== '') { $params['q'] = $query; }
        return 'Admin-articles.html?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }
}
