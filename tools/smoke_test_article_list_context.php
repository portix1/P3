<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit(1); }
require dirname(__DIR__) . '/var/modules/articles/src/ArticleListPagination.php';
use PortixModule\Articles\ArticleListPagination as ListPage;

$origin = ListPage::context(['from_page' => '4', 'from_state' => 'draft', 'from_q' => 'érable & acajou']);
if ($origin !== ['page' => 4, 'state' => 'draft', 'query' => 'érable & acajou']) {
    throw new RuntimeException('Contexte de liste non récupéré');
}
$edit = ListPage::editorUrl(53, $origin['page'], $origin['state'], $origin['query']);
if (!str_starts_with($edit, 'Admin-articles-edit-53.html?')) {
    throw new RuntimeException('Adresse édition incorrecte');
}
parse_str((string)parse_url($edit, PHP_URL_QUERY), $params);
if (ListPage::context($params) !== $origin) {
    throw new RuntimeException('Contexte de la liste non transmis');
}
$back = ListPage::pageUrl($origin['page'], $origin['state'], $origin['query']);
if (!str_contains($back, 'q=%C3%A9rable%20%26%20acajou')) {
    throw new RuntimeException('Recherche non encodée correctement');
}
$invalid = ListPage::context([
    'from_page' => '-4', 'from_state' => 'published&admin=true',
    'from_q' => ['unexpected'],
]);
if ($invalid !== ['page' => 1, 'state' => '', 'query' => '']) {
    throw new RuntimeException('Contexte non fiable accepté');
}
if (ListPage::context(['from_page' => str_repeat('9', 500)])['page'] !== 1000000000) {
    throw new RuntimeException('Numéro de page non limité');
}
$list = (string)file_get_contents(dirname(__DIR__) . '/var/modules/articles/templates/admin-list.tpl');
$form = (string)file_get_contents(dirname(__DIR__) . '/var/modules/articles/templates/admin-form.tpl');
$controller = (string)file_get_contents(dirname(__DIR__) . '/var/modules/articles/src/ArticleAdminController.php');
foreach (['{{ articles_new_url }}', '{{ articlerow.edit_url }}'] as $needle) {
    if (!str_contains($list, $needle)) { throw new RuntimeException('Lien de liste manquant'); }
}
foreach (['articles_return_url', 'name="from_page"', 'name="from_state"', 'name="from_q"'] as $needle) {
    if (!str_contains($form, $needle)) { throw new RuntimeException('Contexte absent du formulaire'); }
}
if (!str_contains($controller, "ArticleListPagination::context(")
    || !str_contains($controller, "header('Location: ' . \$returnUrl, true, 303)")) {
    throw new RuntimeException('Retour après enregistrement non configuré');
}
echo "Contexte des articles, filtres et retour sécurisé : OK\n";
