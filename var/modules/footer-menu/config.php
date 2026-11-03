<?php
require_once __DIR__ . '/src/FooterMenuRepository.php';

use PortixModule\FooterMenu\FooterMenuRepository;

PortixSecurity::requireRole($user, 'admin');

$footerRepository = new FooterMenuRepository($database->connection());
$footerRepository->ensureDefaults();
$footerMessage = '';

$normaliseFooterUrl = static function (string $value) use ($translator): string {
    $value = trim($value);
    if ($value === '' || preg_match('/[\x00-\x1F\x7F]/', $value)) {
        throw new InvalidArgumentException($translator->text('footer_invalid_url'));
    }

    $scheme = parse_url($value, PHP_URL_SCHEME);
    if (is_string($scheme) && $scheme !== '') {
        if (!in_array(strtolower($scheme), ['http', 'https'], true)
            || !is_string(parse_url($value, PHP_URL_HOST))
            || parse_url($value, PHP_URL_HOST) === '') {
            throw new InvalidArgumentException($translator->text('footer_invalid_url'));
        }
        return $value;
    }

    if (str_starts_with($value, '//')
        || str_starts_with($value, '/')
        || preg_match('!^[A-Za-z0-9][A-Za-z0-9._~%/?#=&+\-]*$!', $value) !== 1) {
        throw new InvalidArgumentException($translator->text('footer_invalid_url'));
    }

    return $value;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && (string) ($_POST['footer_action'] ?? '') === 'save') {
    try {
        PortixSecurity::requirePostWithCsrf();

        $columnCount = (int) ($_POST['column_count'] ?? 3);
        if (!in_array($columnCount, [2, 3], true)) {
            throw new InvalidArgumentException($translator->text('footer_invalid_column_count'));
        }

        $columnIds = isset($_POST['column_id']) && is_array($_POST['column_id'])
            ? array_map('intval', $_POST['column_id'])
            : [];
        $columnTitles = isset($_POST['column_title']) && is_array($_POST['column_title'])
            ? $_POST['column_title']
            : [];
        $postedLinks = isset($_POST['links']) && is_array($_POST['links'])
            ? $_POST['links']
            : [];
        $allowedColumns = $footerRepository->columnIdMap();
        $allowedLinks = $footerRepository->linkIdMap();

        $database->transaction(function () use (
            $footerRepository,
            $normaliseFooterUrl,
            $columnCount,
            $columnIds,
            $columnTitles,
            $postedLinks,
            $allowedColumns,
            $allowedLinks
        ): void {
            $footerRepository->setColumnCount($columnCount);

            foreach ($columnIds as $index => $columnId) {
                if (!isset($allowedColumns[$columnId])) {
                    continue;
                }
                $title = trim((string) ($columnTitles[$index] ?? ''));
                if (mb_strlen($title) > 100) {
                    throw new InvalidArgumentException('footer_invalid_title');
                }
                $footerRepository->updateColumn($columnId, $title);
            }

            foreach ($postedLinks as $postedLink) {
                if (!is_array($postedLink)) {
                    continue;
                }

                $id = max(0, (int) ($postedLink['id'] ?? 0));
                $delete = (string) ($postedLink['delete'] ?? '0') === '1';

                if ($delete) {
                    if ($id > 0 && isset($allowedLinks[$id])) {
                        $footerRepository->deleteLink($id);
                    }
                    continue;
                }

                $label = trim((string) ($postedLink['label'] ?? ''));
                $rawUrl = trim((string) ($postedLink['url'] ?? ''));

                // Une ligne neuve restée vide est simplement ignorée.
                if ($id === 0 && $label === '' && $rawUrl === '') {
                    continue;
                }

                $columnId = (int) ($postedLink['column_id'] ?? 0);
                $position = max(0, min(9999, (int) ($postedLink['position'] ?? 0)));
                if ($label === '' || mb_strlen($label) > 140 || !isset($allowedColumns[$columnId])) {
                    throw new InvalidArgumentException('footer_invalid_link');
                }

                $values = [
                    'column_id' => $columnId,
                    'label' => $label,
                    'url' => $normaliseFooterUrl($rawUrl),
                    'position' => $position,
                    'target_blank' => (string) ($postedLink['target_blank'] ?? '0') === '1' ? 1 : 0,
                    'enabled' => (string) ($postedLink['enabled'] ?? '0') === '1' ? 1 : 0,
                ];

                if ($id > 0) {
                    if (!isset($allowedLinks[$id])) {
                        throw new InvalidArgumentException('footer_invalid_link');
                    }
                    $footerRepository->updateLink($id, $values);
                } else {
                    $footerRepository->createLink($values);
                }
            }
        });

        $footerMessage = $translator->text('footer_saved');
    } catch (Throwable $error) {
        $footerMessage = $translator->exception($error);
    }
}

$footerTextDefaults = [
    'footer_admin_eyebrow' => 'Navigation publique',
    'footer_admin_title' => 'Menu de bas de page',
    'footer_admin_help' => 'Organisez des liens éditables sur deux ou trois colonnes. Le menu public occupe toute la largeur du contenu.',
    'footer_layout_title' => 'Disposition',
    'footer_column_count_label' => 'Nombre de colonnes',
    'footer_column_count_help' => 'La troisième colonne reste enregistrée lorsqu’un affichage sur deux colonnes est sélectionné.',
    'footer_column_label' => 'Colonne',
    'footer_column_title_label' => 'Titre',
    'footer_links_title' => 'Liens',
    'footer_drag_help' => 'Ajoutez les liens à la volée avec +, puis glissez-les pour les réordonner ou les déplacer d’une colonne à l’autre.',
    'footer_drag_handle' => 'Déplacer ce lien',
    'footer_label' => 'Libellé',
    'footer_url' => 'URL',
    'footer_visible' => 'Visible',
    'footer_new_tab' => 'Nouvel onglet',
    'footer_add_link' => 'Ajouter un lien',
    'footer_save' => 'Enregistrer le menu',
];
foreach ($footerTextDefaults as $footerTextKey => $footerTextFallback) {
    $template->set($footerTextKey, $translator->text($footerTextKey, $footerTextFallback));
}

$template->setSlot('main', __DIR__ . '/templates/admin.tpl');
$template->set('footer_message', $footerMessage);
$template->set('footer_column_count', (string) $footerRepository->columnCount());
$template->set('footer_columns_2_selected', $footerRepository->columnCount() === 2 ? 'selected' : '');
$template->set('footer_columns_3_selected', $footerRepository->columnCount() === 3 ? 'selected' : '');

$columnCount = $footerRepository->columnCount();
$columns = $footerRepository->columns();
$linksByColumn = [];
foreach ($columns as $column) {
    $linksByColumn[(int) $column['id']] = [];
}

foreach ($footerRepository->links() as $link) {
    $columnId = (int) $link['column_id'];
    if (!isset($linksByColumn[$columnId])) {
        continue;
    }
    $linksByColumn[$columnId][] = [
        'key' => 'link_' . (string) $link['id'],
        'id' => (string) $link['id'],
        'label' => (string) $link['label'],
        'url' => (string) $link['url'],
        'position' => (string) $link['position'],
        'enabled_checked' => !empty($link['enabled']) ? 'checked' : '',
        'target_blank_checked' => !empty($link['target_blank']) ? 'checked' : '',
    ];
}

foreach ($columns as $column) {
    $columnId = (int) $column['id'];
    $template->addRow('footer_admin_columns', [
        'id' => (string) $column['id'],
        'position' => (string) $column['position'],
        'title' => (string) $column['title'],
        'hidden' => (int) $column['position'] > $columnCount ? '1' : '0',
        'links' => $linksByColumn[$columnId] ?? [],
    ]);
}
