<?php
$query = trim((string) ($_GET['q'] ?? ''));
if (mb_strlen($query) > 120) {
    $query = mb_substr($query, 0, 120);
}

$escape = static fn (string $value): string => htmlspecialchars(
    $value,
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);
// Le point d’extension accepte du HTML : le formulaire reste dans le flux
// du menu et ne déclare aucun sous-menu à ouvrir.
$navigationContent = '<link rel="stylesheet" href="var/modules/search/assets/search.css?v=1.0.3" />'
    . '<script src="var/modules/search/assets/search-highlight.js?v=1.0.3" defer></script>'
    . '<form method="get" action="search.html" class="search-nav-form" role="search" '
    . 'aria-label="' . $escape($translator->text('search_nav_label', 'Recherche')) . '">'
    . '<label for="search-nav-query" class="search-visually-hidden">'
    . $escape($translator->text('search_label', 'Termes à rechercher')) . '</label>'
    . '<input id="search-nav-query" type="search" name="q" minlength="2" maxlength="120" required '
    . 'value="' . $escape($query) . '" '
    . 'placeholder="' . $escape($translator->text('search_placeholder_short', 'Rechercher…')) . '" />'
    . '<button type="submit" aria-label="' . $escape($translator->text('search_submit', 'Rechercher')) . '">'
    . '<svg viewBox="0 0 20 20" width="18" height="18" fill="none" stroke="currentColor" '
    . 'stroke-width="1.8" aria-hidden="true" focusable="false">'
    . '<circle cx="8.5" cy="8.5" r="5.5" /><path d="m12.5 12.5 4.5 4.5" /></svg>'
    . '</button></form>';

$template->addRow('navigation', [
    'name' => $translator->text('search_nav_label', 'Recherche'),
    'link' => 'search.html',
    'current' => ($route ?? '') === 'search' ? 'is-current' : '',
    'item_class' => 'navigation-search',
    'has_submenu' => '0',
    'submenu_id' => '',
    'toggle_label' => '',
    'submenu' => \Portix\View\Html::trusted($navigationContent),
]);
