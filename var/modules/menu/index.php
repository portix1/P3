<?php
$showAccessibilityPage = ($argument[0] ?? '') === 'menu' && ($argument[1] ?? '') === 'waa';
foreach ($repositories->menu()->allOrdered() as $res) {
    $menuName = (string) $res['name'];
    $menuLink = (string) $res['url'];
    $menuDescription = (string) $res['description'];
    if ($showAccessibilityPage) {
        $template->addRow('menusimple', [
            'name' => $menuName,
            'url' => $menuLink,
            'desc' => $menuDescription,
        ]);
    }
    $menuUrl = trim((string) $res['url']);
    $menuPath = (string) parse_url($menuUrl, PHP_URL_PATH);
    $menuFullRoute = preg_replace('/\.html$/i', '', basename($menuPath)) ?? '';
    $homeRoute = \Portix\Http\Request::normalizeHomePageTarget((string) $CONFIG['defaultpage']);
    $isHomeMenuItem = strcasecmp($menuFullRoute, 'accueil') === 0
        || ($homeRoute !== null && strcasecmp($menuFullRoute, $homeRoute) === 0);
    if ($isHomeMenuItem) {
        $menuRouteName = $homeRoute ?? 'news';
        $menuLink = ($request->basePath() !== '' ? $request->basePath() : '') . '/';
        $menuCurrent = $request->isFrontPage() ? 'is-current' : '';
    } else {
        $menuRoute = preg_split('/-/', $menuFullRoute, 2);
        $menuRouteName = isset($menuRoute[0]) ? (string) $menuRoute[0] : '';
        $menuCurrent = strcasecmp($menuRouteName, $route) === 0 ? 'is-current' : '';
    }
    $template->addRow('navigation', [
        'name' => $menuName,
        'link' => $menuLink,
        'current' => $menuCurrent,
        'item_class' => '',
        'has_submenu' => '0',
        'submenu_id' => '',
        'toggle_label' => '',
        'submenu' => '',
    ]);
}
if ($showAccessibilityPage) {
    $template->setSlot('main', 'var/modules/menu/templates/access.tpl');
}
?>
