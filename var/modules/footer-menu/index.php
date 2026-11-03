<?php
if (($route ?? '') === 'Admin') {
    return;
}

require_once __DIR__ . '/src/FooterMenuRepository.php';

try {
    $footerRepository = new \PortixModule\FooterMenu\FooterMenuRepository($database->connection());
    $footerRepository->ensureDefaults();

    $footerColumns = [];
    $hasLinks = false;
    foreach ($footerRepository->publicColumns() as $column) {
        $links = [];
        foreach ($footerRepository->publicLinksForColumn((int) $column['id']) as $link) {
            $links[] = [
                'label' => (string) $link['label'],
                'url' => (string) $link['url'],
                'target_blank' => !empty($link['target_blank']) ? '1' : '0',
            ];
        }
        if ($links === []) {
            continue;
        }
        $hasLinks = true;
        $footerColumns[] = [
            'title' => (string) $column['title'],
            'links' => $links,
        ];
    }

    if ($hasLinks) {
        $template->set('footer_menu_aria', $translator->text('footer_public_navigation'));
        $template->set('footer_column_count', (string) max(1, count($footerColumns)));
        foreach ($footerColumns as $column) {
            $template->addRow('footer_columns', $column);
        }
        $template->setSlot('footer', __DIR__ . '/templates/footer.tpl');
    }
} catch (Throwable $error) {
    error_log('FooterMenu: ' . $error->getMessage());
}
