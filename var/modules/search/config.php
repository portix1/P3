<?php
declare(strict_types=1);

require_once __DIR__ . '/src/SearchRepository.php';

use PortixModule\Search\SearchRepository;

PortixSecurity::requireRole($user, 'admin');

$repository = new SearchRepository(
    $database->connection(),
    __DIR__ . '/data/search.sqlite'
);
$message = '';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST'
        && (string) ($_POST['search_action'] ?? '') === 'reindex') {
        PortixSecurity::requirePostWithCsrf();
        $sync = $repository->sync(true);
        $message = sprintf(
            $translator->text('search_admin_reindexed', 'Index reconstruit : %d contenus.'),
            (int) $sync['articles'] + (int) $sync['news']
        );
    } else {
        $repository->sync();
    }
    $stats = $repository->stats();
} catch (Throwable $error) {
    error_log('Search admin: ' . $error->getMessage());
    $stats = [
        'mode' => 'error',
        'indexed' => 0,
        'articles' => 0,
        'news' => 0,
        'last_sync' => '',
    ];
    $message = $translator->text('search_admin_error', 'Impossible de préparer l’index de recherche.');
}

$defaults = [
    'search_admin_eyebrow' => 'Recherche publique',
    'search_admin_title' => 'Moteur de recherche',
    'search_admin_help' => 'La recherche indexe les articles et actualités publiés. L’index se resynchronise automatiquement lorsque les contenus changent.',
    'search_admin_mode_label' => 'Moteur',
    'search_admin_mode_fts5' => 'SQLite FTS5',
    'search_admin_mode_fallback' => 'Compatibilité sans FTS5',
    'search_admin_mode_error' => 'Indisponible',
    'search_admin_indexed_label' => 'Contenus indexés',
    'search_admin_articles_label' => 'Articles publiés',
    'search_admin_news_label' => 'Actualités publiées',
    'search_admin_last_sync_label' => 'Dernière synchronisation',
    'search_admin_never' => 'Jamais',
    'search_admin_reindex' => 'Réindexer maintenant',
    'search_admin_reindexed' => 'Index reconstruit : %d contenus.',
    'search_admin_error' => 'Impossible de préparer l’index de recherche.',
];
foreach ($defaults as $key => $fallback) {
    $template->set($key, $translator->text($key, $fallback));
}

$modeKey = 'search_admin_mode_' . $stats['mode'];
$template->set(
    'search_admin_mode',
    $translator->text($modeKey, ucfirst((string) $stats['mode']))
);
$template->set('search_admin_indexed', (string) $stats['indexed']);
$template->set('search_admin_articles', (string) $stats['articles']);
$template->set('search_admin_news', (string) $stats['news']);
$template->set(
    'search_admin_last_sync',
    $stats['last_sync'] !== ''
        ? (string) $stats['last_sync']
        : $translator->text('search_admin_never', 'Jamais')
);
$template->set('search_admin_message', $message);
$template->setSlot('main', __DIR__ . '/templates/admin.tpl');
