<?php
declare(strict_types=1);

use Portix\Observability\EventLogger;
use PortixModule\Affiliation\AffiliationRepository;

PortixSecurity::requireRole($user, 'admin');
require_once __DIR__ . '/src/AffiliationRepository.php';
$repo = new AffiliationRepository($database->connection());
$logger = new EventLogger($database->connection());
$view = (string) ($argument[2] ?? 'products');
$message = '';
$productViewId = null;

// Initialize every translated label used by the administration templates.
// The template engine is strict: an omitted variable aborts the whole page.
$affiliationTemplateLabels = [
    'affiliation_actions',
    'affiliation_active',
    'affiliation_add_link',
    'affiliation_add_merchant',
    'affiliation_add_merchant_link',
    'affiliation_add_offer',
    'affiliation_add_partner',
    'affiliation_add_product',
    'affiliation_article_selector_help',
    'affiliation_articles',
    'affiliation_articles_none',
    'affiliation_button_help',
    'affiliation_button_label',
    'affiliation_create_article',
    'affiliation_delete',
    'affiliation_delete_link',
    'affiliation_delete_offer_confirm',
    'affiliation_delete_partner_confirm',
    'affiliation_delete_product_confirm',
    'affiliation_description',
    'affiliation_disabled',
    'affiliation_display_in_articles',
    'affiliation_edit',
    'affiliation_enabled',
    'affiliation_filter_articles',
    'affiliation_filter_articles_placeholder',
    'affiliation_image',
    'affiliation_image_help',
    'affiliation_intro',
    'affiliation_manage',
    'affiliation_merchant',
    'affiliation_merchant_form_help',
    'affiliation_merchant_links',
    'affiliation_merchants',
    'affiliation_merchants_help',
    'affiliation_merchants_summary',
    'affiliation_name',
    'affiliation_navigation',
    'affiliation_need_merchant',
    'affiliation_no_merchants_body',
    'affiliation_no_merchants_title',
    'affiliation_no_offers_body',
    'affiliation_no_offers_title',
    'affiliation_no_products_body_simple',
    'affiliation_no_products_title',
    'affiliation_offer_form_help',
    'affiliation_offer_label',
    'affiliation_offer_prerequisite_help',
    'affiliation_offer_prerequisite_title',
    'affiliation_offer_product_help',
    'affiliation_offer_url_help',
    'affiliation_offers',
    'affiliation_offers_summary',
    'affiliation_open_partner',
    'affiliation_overview',
    'affiliation_partner',
    'affiliation_partner_website_help',
    'affiliation_product',
    'affiliation_product_description_help',
    'affiliation_product_details',
    'affiliation_product_workspace_help',
    'affiliation_products',
    'affiliation_products_home_help',
    'affiliation_save',
    'affiliation_save_before_links',
    'affiliation_save_link',
    'affiliation_save_product',
    'affiliation_settings',
    'affiliation_settings_disclosure_help',
    'affiliation_settings_disclosure_label',
    'affiliation_settings_help',
    'affiliation_settings_max_help',
    'affiliation_settings_max_label',
    'affiliation_settings_title_help',
    'affiliation_settings_title_label',
    'affiliation_state',
    'affiliation_title',
    'affiliation_url',
    'affiliation_visibility_help',
    'affiliation_website',
    'affiliation_where_to_buy',
    'affiliation_where_to_buy_help',
];
foreach ($affiliationTemplateLabels as $labelKey) {
    $template->set($labelKey, $translator->text($labelKey));
}

if (isset($_POST['affiliation_action'])) {
    try {
        PortixSecurity::requirePostWithCsrf();
        $action = (string) $_POST['affiliation_action'];

        if ($action === 'save_partner') {
            $id = affiliationOptionalId($_POST['id'] ?? null);
            $saved = $repo->savePartner(
                $id,
                (string) ($_POST['name'] ?? ''),
                (string) ($_POST['website_url'] ?? ''),
                isset($_POST['enabled'])
            );
            $logger->audit($user->pseudo(), $id === null ? 'affiliation.partner_create' : 'affiliation.partner_update', 'affiliate_partner:' . $saved, [], $requestId);
            $message = $translator->text('affiliation_partner_saved');
            $view = 'partners';
        } elseif ($action === 'delete_partner') {
            $id = affiliationRequiredId($_POST['id'] ?? null);
            $repo->deletePartner($id);
            $logger->audit($user->pseudo(), 'affiliation.partner_delete', 'affiliate_partner:' . $id, [], $requestId);
            $message = $translator->text('affiliation_partner_deleted');
            $view = 'partners';
        } elseif ($action === 'save_product') {
            $id = affiliationOptionalId($_POST['id'] ?? null);
            $articleIds = is_array($_POST['article_ids'] ?? null) ? $_POST['article_ids'] : [];
            $saved = $repo->saveProduct(
                $id,
                (string) ($_POST['name'] ?? ''),
                (string) ($_POST['description'] ?? ''),
                (string) ($_POST['image_url'] ?? ''),
                isset($_POST['enabled']),
                $articleIds
            );
            $logger->audit($user->pseudo(), $id === null ? 'affiliation.product_create' : 'affiliation.product_update', 'affiliate_product:' . $saved, [], $requestId);
            $message = $translator->text('affiliation_product_saved');
            $view = 'product';
            $productViewId = $saved;
        } elseif ($action === 'delete_product') {
            $id = affiliationRequiredId($_POST['id'] ?? null);
            $repo->deleteProduct($id);
            $logger->audit($user->pseudo(), 'affiliation.product_delete', 'affiliate_product:' . $id, [], $requestId);
            $message = $translator->text('affiliation_product_deleted');
            $view = 'products';
        } elseif ($action === 'save_offer') {
            $id = affiliationOptionalId($_POST['id'] ?? null);
            $productId = affiliationRequiredId($_POST['product_id'] ?? null);
            $saved = $repo->saveOffer(
                $id,
                $productId,
                affiliationRequiredId($_POST['partner_id'] ?? null),
                (string) ($_POST['affiliate_url'] ?? ''),
                (string) ($_POST['button_label'] ?? ''),
                isset($_POST['enabled'])
            );
            $logger->audit($user->pseudo(), $id === null ? 'affiliation.offer_create' : 'affiliation.offer_update', 'affiliate_offer:' . $saved, [], $requestId);
            $message = $translator->text('affiliation_offer_saved');
            $view = 'product';
            $productViewId = $productId;
        } elseif ($action === 'delete_offer') {
            $id = affiliationRequiredId($_POST['id'] ?? null);
            $offer = $repo->offer($id);
            if ($offer === null) {
                throw new RuntimeException('affiliation_not_found');
            }
            $productViewId = (int) $offer['product_id'];
            $repo->deleteOffer($id);
            $logger->audit($user->pseudo(), 'affiliation.offer_delete', 'affiliate_offer:' . $id, [], $requestId);
            $message = $translator->text('affiliation_offer_deleted');
            $view = 'product';
        } elseif ($action === 'settings') {
            $title = trim((string) ($_POST['title'] ?? ''));
            $disclosure = trim((string) ($_POST['disclosure'] ?? ''));
            if ($title === '' || mb_strlen($title) > 160 || mb_strlen($disclosure) > 500) {
                throw new RuntimeException('affiliation_invalid_settings');
            }
            PortixSettings::write('affiliation', [
                'title' => $title,
                'disclosure' => $disclosure,
                'max_products' => max(1, min(12, (int) ($_POST['max_products'] ?? 4))),
            ]);
            $message = $translator->text('affiliation_settings_saved');
            $view = 'settings';
        }
    } catch (Throwable $error) {
        $message = $translator->exception($error);
    }
}

// Old offer routes remain harmless, but the administration is now product-centric.
if ($view === 'offers' || $view === 'newoffer') {
    $view = 'products';
}
if ($view === 'offer') {
    $legacyOffer = $repo->offer(affiliationRequiredId($argument[3] ?? null));
    if ($legacyOffer !== null) {
        $productViewId = (int) $legacyOffer['product_id'];
        $view = 'product';
    } else {
        $view = 'products';
    }
}

$products = $repo->products();
$partners = $repo->partners();
$offers = $repo->offers();
$articles = $repo->articles();

$template->set('affiliation_products_count', (string) count($products));
$template->set('affiliation_partners_count', (string) count($partners));
$template->set('affiliation_offers_count', (string) count($offers));
$template->set('affiliation_has_products', $products !== [] ? '1' : '0');
$template->set('affiliation_has_partners', $partners !== [] ? '1' : '0');
$template->set('affiliation_has_articles', $articles !== [] ? '1' : '0');
$template->set('affiliation_message', $message);

$activeTab = match ($view) {
    'product', 'newproduct' => 'products',
    'partner', 'newpartner' => 'partners',
    default => $view,
};
foreach (['products', 'partners', 'settings'] as $tab) {
    $template->set('affiliation_' . $tab . '_current', $activeTab === $tab ? 'is-current' : '');
}

if ($view === 'partners') {
    $template->setSlot('main', 'var/modules/affiliation/templates/partners.tpl');
    foreach ($partners as $row) {
        $template->addRow('affpartner', [
            'id' => (string) $row['id'],
            'name' => (string) $row['name'],
            'website_url' => (string) $row['website_url'],
            'offers' => (string) ($row['offer_count'] ?? 0),
            'state' => (int) $row['enabled'] === 1 ? $translator->text('affiliation_enabled') : $translator->text('affiliation_disabled'),
            'state_class' => (int) $row['enabled'] === 1 ? 'state-enabled' : 'state-disabled',
        ]);
    }
    return;
}

if ($view === 'partner' || $view === 'newpartner') {
    $row = $view === 'partner' ? $repo->partner(affiliationRequiredId($argument[3] ?? null)) : null;
    if ($view === 'partner' && $row === null) {
        throw new RuntimeException($translator->text('affiliation_not_found'));
    }
    $template->setSlot('main', 'var/modules/affiliation/templates/partner-form.tpl');
    $template->set('affiliation_form_heading', $translator->text($row === null ? 'affiliation_new_partner' : 'affiliation_edit_partner'));
    $template->set('affiliation_form_id', (string) ($row['id'] ?? ''));
    $template->set('affiliation_form_name', (string) ($row['name'] ?? ''));
    $template->set('affiliation_form_website', (string) ($row['website_url'] ?? ''));
    $template->set('affiliation_form_enabled', $row === null || (int) $row['enabled'] === 1 ? 'checked' : '');
    return;
}

if ($view === 'settings') {
    $settings = PortixSettings::read('affiliation', [
        'title' => $translator->text('affiliation_public_title'),
        'disclosure' => $translator->text('affiliation_public_disclosure'),
        'max_products' => 4,
    ]);
    $template->setSlot('main', 'var/modules/affiliation/templates/settings.tpl');
    $template->set('affiliation_settings_title', (string) $settings['title']);
    $template->set('affiliation_settings_disclosure', (string) $settings['disclosure']);
    $template->set('affiliation_settings_max', (string) $settings['max_products']);
    return;
}

if ($view === 'product' || $view === 'newproduct') {
    $id = $productViewId;
    if ($id === null && $view === 'product') {
        $id = affiliationRequiredId($argument[3] ?? null);
    }
    $row = $id !== null ? $repo->product($id) : null;
    if ($id !== null && $row === null) {
        throw new RuntimeException($translator->text('affiliation_not_found'));
    }

    $selected = $row === null ? [] : $repo->productArticleIds((int) $row['id']);
    $productOffers = $row === null ? [] : $repo->offersForProduct((int) $row['id']);

    $template->setSlot('main', 'var/modules/affiliation/templates/product-form.tpl');
    $template->set('affiliation_form_heading', $translator->text($row === null ? 'affiliation_new_product' : 'affiliation_edit_product'));
    $template->set('affiliation_form_id', (string) ($row['id'] ?? ''));
    $template->set('affiliation_form_name', (string) ($row['name'] ?? ''));
    $template->set('affiliation_form_description', (string) ($row['description'] ?? ''));
    $template->set('affiliation_form_image', (string) ($row['image_url'] ?? ''));
    $template->set('affiliation_form_enabled', $row === null || (int) $row['enabled'] === 1 ? 'checked' : '');
    $template->set('affiliation_product_exists', $row !== null ? '1' : '0');
    $template->set('affiliation_product_offer_count', (string) count($productOffers));
    $template->set('affiliation_product_has_offers', $productOffers !== [] ? '1' : '0');

    foreach ($articles as $article) {
        $template->addRow('affarticleoption', [
            'id' => (string) $article['id'],
            'title' => (string) $article['title'],
            'category_path' => (string) ($article['category_path'] ?? ''),
            'status' => $translator->text((string) $article['status'] === 'published' ? 'affiliation_status_published' : 'affiliation_status_draft'),
            'checked' => in_array((int) $article['id'], $selected, true) ? 'checked' : '',
        ]);
    }

    foreach ($partners as $partner) {
        $template->addRow('affpartneroption', [
            'id' => (string) $partner['id'],
            'name' => (string) $partner['name'],
        ]);
    }

    foreach ($productOffers as $offer) {
        foreach ($partners as $partner) {
            $template->addRow('affofferpartner' . (string) $offer['id'], [
                'id' => (string) $partner['id'],
                'name' => (string) $partner['name'],
                'selected' => (int) $offer['partner_id'] === (int) $partner['id'] ? 'selected' : '',
            ]);
        }
        $template->addRow('affproductoffer', [
            'id' => (string) $offer['id'],
            'product_id' => (string) $offer['product_id'],
            'partner' => (string) $offer['partner_name'],
            'partner_id' => (string) $offer['partner_id'],
            'url' => (string) $offer['affiliate_url'],
            'label' => (string) $offer['button_label'],
            'enabled' => (int) $offer['enabled'] === 1 ? 'checked' : '',
            'partner_rows' => 'affofferpartner' . (string) $offer['id'],
        ]);
    }
    return;
}

$template->setSlot('main', 'var/modules/affiliation/templates/products.tpl');
foreach ($products as $row) {
    $template->addRow('affproduct', [
        'id' => (string) $row['id'],
        'name' => (string) $row['name'],
        'offers' => (string) $row['offer_count'],
        'articles' => (string) $row['article_count'],
        'state' => (int) $row['enabled'] === 1 ? $translator->text('affiliation_enabled') : $translator->text('affiliation_disabled'),
        'state_class' => (int) $row['enabled'] === 1 ? 'state-enabled' : 'state-disabled',
    ]);
}

function affiliationOptionalId(mixed $value): ?int
{
    if ($value === null || $value === '') {
        return null;
    }
    return affiliationRequiredId($value);
}

function affiliationRequiredId(mixed $value): int
{
    if (!is_scalar($value) || !ctype_digit((string) $value) || (int) $value < 1) {
        throw new RuntimeException('affiliation_invalid_id');
    }
    return (int) $value;
}
