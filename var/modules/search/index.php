<?php
declare(strict_types=1);

require_once __DIR__ . '/src/SearchRepository.php';

use PortixModule\Search\SearchRepository;

header('X-Robots-Tag: noindex, follow');
$pageMetadata->define(
    $translator->text('search_title', 'Recherche'),
    $translator->text('search_meta_description', 'Rechercher dans les articles et les actualités du site.'),
    'search.html'
);
$pageMetadata->noIndex();

$template->setSlot('main', __DIR__ . '/templates/index.tpl');

$searchTextDefaults = [
    'search_eyebrow' => 'Recherche',
    'search_title' => 'Rechercher sur le site',
    'search_label' => 'Termes à rechercher',
    'search_placeholder' => 'Ex. Telecaster, diapason, humbucker…',
    'search_submit' => 'Rechercher',
    'search_filter_label' => 'Dans',
    'search_filter_all' => 'Tout',
    'search_filter_articles' => 'Articles',
    'search_filter_news' => 'Actualités',
    'search_start_help' => 'Saisissez au moins deux caractères.',
    'search_no_results' => 'Aucun résultat pour cette recherche.',
    'search_results_one' => '1 résultat',
    'search_results_many' => '%d résultats',
    'search_kind_article' => 'Article',
    'search_kind_news' => 'Actualité',
    'search_mode_fallback' => 'Recherche en mode compatibilité.',
    'search_error' => 'La recherche est momentanément indisponible.',
];
foreach ($searchTextDefaults as $key => $fallback) {
    $template->set($key, $translator->text($key, $fallback));
}

$query = trim((string) ($_GET['q'] ?? ''));
if (mb_strlen($query) > 120) {
    $query = mb_substr($query, 0, 120);
}
$filter = (string) ($_GET['type'] ?? 'all');
if (!in_array($filter, ['all', 'article', 'news'], true)) {
    $filter = 'all';
}

$template->set('search_query', $query);
$template->set('search_filter_all_selected', $filter === 'all' ? 'selected' : '');
$template->set('search_filter_article_selected', $filter === 'article' ? 'selected' : '');
$template->set('search_filter_news_selected', $filter === 'news' ? 'selected' : '');
$template->set('search_has_query', mb_strlen($query) >= 2 ? '1' : '0');
$template->set('search_has_results', '0');
$template->set('search_result_count', '0');
$template->set('search_result_message', '');
$template->set('search_status_message', '');

if (mb_strlen($query) < 2) {
    return;
}

try {
    $repository = new SearchRepository(
        $database->connection(),
        __DIR__ . '/data/search.sqlite'
    );
    $sync = $repository->sync();
    if ($sync['mode'] === 'fallback') {
        $template->set(
            'search_status_message',
            $translator->text('search_mode_fallback', 'Recherche en mode compatibilité.')
        );
    }

    $results = $repository->search($query, $filter, 50);
    if ($repository->suggestedQuery() !== '') {
        $template->set('search_status_message', sprintf(
            $translator->text('search_approximate', 'Résultats approchants pour : %s'),
            $repository->suggestedQuery()
        ));
    }
    $count = count($results);
    $template->set('search_result_count', (string) $count);
    $template->set('search_has_results', $count > 0 ? '1' : '0');
    $template->set(
        'search_result_message',
        $count === 1
            ? $translator->text('search_results_one', '1 résultat')
            : sprintf($translator->text('search_results_many', '%d résultats'), $count)
    );

    foreach ($results as $result) {
        $template->addRow('searchresult', [
            'kind' => $result['kind'],
            'kind_label' => $result['kind'] === 'news'
                ? $translator->text('search_kind_news', 'Actualité')
                : $translator->text('search_kind_article', 'Article'),
            'url' => $result['url'],
            'title' => $result['title'],
            'category' => $result['category'],
            'excerpt' => $result['excerpt'],
        ]);
    }
} catch (Throwable $error) {
    error_log('Search: ' . $error->getMessage());
    $template->set(
        'search_status_message',
        $translator->text('search_error', 'La recherche est momentanément indisponible.')
    );
}
