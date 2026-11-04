<?php
PortixSecurity::requireRole($user, 'admin');
$configSection = (string) ($argument[2] ?? 'identity');
if (!in_array($configSection, ['identity', 'seo', 'mail', 'appearance'], true)) {
    http_response_code(404);
    $template->setSlot('main', './var/admin/templates/not-found.tpl');
    return;
}
if (isset($_POST['ux_section'])) {
    if ($_POST['ux_section'] !== $configSection) {
        http_response_code(400);
        $template->setSlot('main', './var/admin/templates/not-found.tpl');
        return;
    }
    $sectionFields = [
        'identity' => ['conftitle', 'site_url', 'site_description', 'langage', 'defpage'],
        'seo' => ['seo_home_title', 'seo_home_description', 'seo_default_author', 'seo_publisher_name', 'seo_publisher_logo', 'seo_default_image'],
        'mail' => ['mail_sender_name', 'mail_sender_email'],
        'appearance' => ['templ'],
    ];
    $fieldConfig = [
        'conftitle' => 'title', 'langage' => 'lang', 'defpage' => 'defaultpage', 'templ' => 'template',
    ];
    foreach (array_merge(...array_values($sectionFields)) as $field) {
        if (!in_array($field, $sectionFields[$configSection], true)) {
            $_POST[$field] = (string) ($CONFIG[$fieldConfig[$field] ?? $field] ?? '');
        }
    }
}

$themeCatalog = new \Portix\Theme\ThemeCatalog(dirname(__DIR__), trim((string) file_get_contents(dirname(__DIR__) . '/VERSION')));
$themeInventory = $themeCatalog->all();
$themeById = [];
foreach ($themeInventory as $themeEntry) { $themeById[$themeEntry['id']] = $themeEntry; }
$languages = array();
foreach (glob('./var/lang/*.php') as $languageFile) {
    $languages[] = basename($languageFile, '.php');
}

$templates = array();
foreach (glob('./var/templates/*', GLOB_ONLYDIR) as $templateDirectory) {
    if (!is_link($templateDirectory) && is_file($templateDirectory . '/index.tpl')) {
        $templates[] = basename($templateDirectory);
    }
}
sort($templates, SORT_NATURAL | SORT_FLAG_CASE);
if (($modernIndex = array_search('modern', $templates, true)) !== false && $modernIndex > 0) {
    unset($templates[$modernIndex]);
    array_unshift($templates, 'modern');
    $templates = array_values($templates);
}
$themeLabels = [
    'modern' => 'Modern',
    'editorial' => 'Editorial',
    'terminal' => 'Terminal',
    'brutal' => 'Brutal',
    'sidebar-left' => 'Sidebar gauche',
    'sidebar-right' => 'Sidebar droite',
    'cupertino' => 'Cupertino',
    'univers-guitare' => 'Univers Guitare',
];

$configurationMessage = '';
if (isset($_POST['langage'])) {
    $language = (string) $_POST['langage'];
    $theme = (string) ($_POST['templ'] ?? '');
    $defaultPage = \Portix\Http\Request::normalizeHomePageTarget(
        (string) ($_POST['defpage'] ?? '')
    );
    $title = trim((string) ($_POST['conftitle'] ?? ''));
    $siteUrl = rtrim(trim((string) ($_POST['site_url'] ?? '')), '/');
    $siteDescription = trim((string) ($_POST['site_description'] ?? ''));
    $seoHomeTitle = array_key_exists('seo_home_title', $_POST)
        ? trim(strip_tags((string) $_POST['seo_home_title']))
        : (string) ($CONFIG['seo_home_title'] ?? '');
    $seoHomeDescription = array_key_exists('seo_home_description', $_POST)
        ? trim(strip_tags((string) $_POST['seo_home_description']))
        : (string) ($CONFIG['seo_home_description'] ?? '');
    $seoDefaultAuthor = trim(strip_tags((string) ($_POST['seo_default_author'] ?? '')));
    $seoPublisherName = trim(strip_tags((string) ($_POST['seo_publisher_name'] ?? '')));
    $seoPublisherLogo = trim((string) ($_POST['seo_publisher_logo'] ?? ''));
    $seoDefaultImage = trim((string) ($_POST['seo_default_image'] ?? ''));
    $mailSenderName = trim((string) ($_POST['mail_sender_name'] ?? ''));
    $mailSenderEmail = trim((string) ($_POST['mail_sender_email'] ?? ''));

    try {
        PortixSecurity::requirePostWithCsrf();
        if ($theme !== (string) ($CONFIG['template'] ?? '') && isset($themeById[$theme]) && $themeById[$theme]['compatible'] === false) {
            throw new InvalidArgumentException($translator->text('themes_activation_refused'));
        }
        if (!in_array($language, $languages, true)
            || !in_array($theme, $templates, true)
            || $defaultPage === null
            || $title === ''
            || strlen($title) > 200
            || ($siteUrl !== '' && filter_var($siteUrl, FILTER_VALIDATE_URL) === false)
            || ($siteUrl !== '' && !in_array(strtolower((string) parse_url($siteUrl, PHP_URL_SCHEME)), ['http', 'https'], true))
            || mb_strlen($siteDescription) > 320
            || mb_strlen($seoHomeTitle) > 180
            || mb_strlen($seoHomeDescription) > 170
            || mb_strlen($seoDefaultAuthor) > 160
            || mb_strlen($seoPublisherName) > 160
            || mb_strlen($seoPublisherLogo) > 500
            || mb_strlen($seoDefaultImage) > 500
            || ($seoPublisherLogo !== ''
                && preg_match('#^https?://#i', $seoPublisherLogo) === 1
                && filter_var($seoPublisherLogo, FILTER_VALIDATE_URL) === false)
            || ($seoDefaultImage !== ''
                && preg_match('#^https?://#i', $seoDefaultImage) === 1
                && filter_var($seoDefaultImage, FILTER_VALIDATE_URL) === false)
            || mb_strlen($mailSenderName) > 120
            || ($mailSenderEmail !== '' && filter_var($mailSenderEmail, FILTER_VALIDATE_EMAIL) === false)) {
            throw new InvalidArgumentException($translator->text('config_invalid'));
        } else {
            $configuration->update([
                'lang' => $language,
                'template' => $theme,
                'defaultpage' => $defaultPage,
                'title' => $title,
                'site_url' => $siteUrl,
                'site_description' => $siteDescription,
                'seo_home_title' => $seoHomeTitle,
                'seo_home_description' => $seoHomeDescription,
                'seo_default_author' => $seoDefaultAuthor,
                'seo_publisher_name' => $seoPublisherName,
                'seo_publisher_logo' => $seoPublisherLogo,
                'seo_default_image' => $seoDefaultImage,
                'mail_sender_name' => $mailSenderName,
                'mail_sender_email' => $mailSenderEmail,
            ]);
            $CONFIG = $configuration->all();
            $configurationMessage = $translator->text('config_saved');
        }
    } catch (Throwable $error) {
        $configurationMessage = $translator->exception($error);
    }
}

$template->setSlot('main', './var/admin/templates/config-' . $configSection . '.tpl');
$template->set('confmessage', $configurationMessage);

foreach ($languages as $language) {
    $template->addRow('languages', array(
        'name' => $language,
        'check' => $language === $CONFIG['lang'] ? 'selected' : '',
    ));
}

foreach ($templates as $theme) {
    $template->addRow('templates', array(
        'name' => $theme,
        'label' => isset($themeById[$theme]) && $themeById[$theme]['versioned'] ? $themeById[$theme]['name'] . ' — ' . $themeById[$theme]['version'] : ($themeLabels[$theme] ?? ucfirst($theme)) . ' — ' . $translator->text('themes_not_versioned'),
        'checked' => $theme === $CONFIG['template'] ? 'selected' : '',
    ));
}

$configuredHomePage = \Portix\Http\Request::normalizeHomePageTarget(
    (string) $CONFIG['defaultpage']
) ?? 'news';
$template->set('defaultpg', $configuredHomePage . '.html');
$template->set('title', $CONFIG['title']);
$template->set('site_url', (string) ($CONFIG['site_url'] ?? ''));
$template->set('site_description', (string) ($CONFIG['site_description'] ?? ''));
$template->set('seo_home_title', isset($seoHomeTitle) ? $seoHomeTitle : (string) ($CONFIG['seo_home_title'] ?? ''));
$template->set('seo_home_description', isset($seoHomeDescription) ? $seoHomeDescription : (string) ($CONFIG['seo_home_description'] ?? ''));
$template->set('seo_default_author', (string) ($CONFIG['seo_default_author'] ?? ''));
$template->set('seo_publisher_name', (string) ($CONFIG['seo_publisher_name'] ?? ''));
$template->set('seo_publisher_logo', (string) ($CONFIG['seo_publisher_logo'] ?? ''));
$template->set('seo_default_image', (string) ($CONFIG['seo_default_image'] ?? ''));
$template->set('mail_sender_name', (string) ($CONFIG['mail_sender_name'] ?? ''));
$template->set('mail_sender_email', (string) ($CONFIG['mail_sender_email'] ?? ''));

