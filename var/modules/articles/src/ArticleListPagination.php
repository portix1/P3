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

    /**
     * Links to editor preserve the originating list without accepting a redirect URL.
     */
    public static function editorUrl(?int $id, int $page, string $state, string $query): string
    {
        $path = $id === null ? 'Admin-articles-new.html' : 'Admin-articles-edit-' . $id . '.html';
        $parameters = ['from_page' => max(1, $page)];
        if (in_array($state, ['draft', 'pending', 'published'], true)) {
            $parameters['from_state'] = $state;
        }
        $query = mb_substr(trim($query), 0, 120, 'UTF-8');
        if ($query !== '') { $parameters['from_q'] = $query; }
        return $path . '?' . http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
    }

    /** @param array<string,mixed> $input @return array{page:int,state:string,query:string} */
    public static function context(array $input): array
    {
        $rawPage = $input['from_page'] ?? '1';
        $page = is_string($rawPage) && ctype_digit($rawPage)
            ? (int) min((float) $rawPage, 1000000000) : 1;
        $rawState = $input['from_state'] ?? '';
        $state = is_string($rawState) && in_array($rawState, ['draft', 'pending', 'published'], true)
            ? $rawState : '';
        $rawQuery = $input['from_q'] ?? '';
        $query = is_string($rawQuery) ? mb_substr(trim($rawQuery), 0, 120, 'UTF-8') : '';
        return ['page' => max(1, $page), 'state' => $state, 'query' => $query];
    }

    public static function pageUrl(int $page, string $state, string $query): string
    {
        $params = ['page' => $page];
        if ($state !== '') { $params['state'] = $state; }
        if ($query !== '') { $params['q'] = $query; }
        return 'Admin-articles.html?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }
}
