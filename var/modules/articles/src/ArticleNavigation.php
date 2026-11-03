<?php
declare(strict_types=1);

namespace PortixModule\Articles;

use Portix\Support\Translator;

final class ArticleNavigation
{
    public function __construct(
        private readonly ArticleRepository $articles,
        private readonly Translator $translator
    ) {
    }

    /** @param list<string> $arguments */
    public function append(\Portix\View\Template $template, array $arguments): void
    {
        $settings = $this->articles->menuSettings();
        if (!$settings['header_enabled']) {
            return;
        }

        $categories = $this->articles->visibleCategories();
        if ($categories === []) {
            return;
        }

        $byId = [];
        $children = [];
        foreach ($categories as $category) {
            $id = (int) $category['id'];
            $byId[$id] = $category;
            $parentId = $category['parent_id'] === null ? 0 : (int) $category['parent_id'];
            $children[$parentId][] = $id;
        }

        $activeRoot = $this->activeRootId($arguments, $byId);
        foreach ($children[0] ?? [] as $rootId) {
            $root = $byId[$rootId];
            $outline = $this->articles->publicOutline($rootId);
            $submenu = $this->renderContents($outline['items'] ?? [], $settings['show_articles']);
            $template->addRow('navigation', [
                'name' => (string) $root['name'],
                'link' => 'articles-categorie-' . (string) $root['slug'] . '.html',
                'current' => $activeRoot === $rootId ? 'is-current' : '',
                'item_class' => $submenu !== '' ? 'has-submenu' : '',
                'has_submenu' => $submenu !== '' ? '1' : '0',
                'submenu_id' => 'articles-submenu-' . $rootId,
                'toggle_label' => sprintf(
                    $this->translator->text('articles_menu_toggle'),
                    (string) $root['name']
                ),
                'has_header_submenu' => count(array_filter($outline['items'] ?? [], static fn (array $item): bool => ($item['type'] ?? '') === 'category')) > 0 ? '1' : '0',
                'submenu' => $submenu === '' ? '' : \Portix\View\Html::trusted(
                    '<ul class="navigation-submenu" id="articles-submenu-' . $rootId . '">'
                    . $submenu . '</ul>'
                ),
            ]);
        }
    }

    /** @param list<array<string, mixed>> $items */
    private function renderContents(array $items, bool $showArticles): string
    {
        $html = '';
        foreach ($items as $item) {
            if (($item['type'] ?? '') === 'article' && is_array($item['article'] ?? null)) {
                if (!$showArticles) {
                    continue;
                }
                $article = $item['article'];
                $html .= '<li class="navigation-article"><a href="'
                    . rawurlencode((string) $article['slug']) . '.html">'
                    . $this->escape((string) $article['title']) . '</a></li>';
                continue;
            }
            if (($item['type'] ?? '') !== 'category' || !is_array($item['category'] ?? null)) {
                continue;
            }
            $child = $item['category'];
            $childId = (int) $child['id'];
            $nested = $this->renderContents(
                is_array($item['items'] ?? null) ? $item['items'] : [],
                $showArticles
            );

            $name = $this->escape((string) $child['name']);
            $url = 'articles-categorie-' . rawurlencode((string) $child['slug']) . '.html';
            $html .= '<li class="navigation-submenu-item' . ($nested !== '' ? ' has-submenu' : '') . '">'
                . '<div class="navigation-link-row"><a href="' . $url . '">' . $name . '</a>';
            if ($nested !== '') {
                $submenuId = 'articles-submenu-' . $childId;
                $label = sprintf($this->translator->text('articles_menu_toggle'), (string) $child['name']);
                $html .= '<button class="submenu-toggle" type="button" data-submenu-toggle '
                    . 'aria-expanded="false" aria-controls="' . $submenuId . '" aria-label="'
                    . $this->escape($label) . '"><span aria-hidden="true">▾</span></button>';
            }
            $html .= '</div>';
            if ($nested !== '') {
                $html .= '<ul class="navigation-submenu" id="articles-submenu-' . $childId . '">'
                    . $nested . '</ul>';
            }
            $html .= '</li>';
        }
        return $html;
    }

    /** @param array<int, array<string, mixed>> $byId */
    private function activeRootId(array $arguments, array $byId): ?int
    {
        if (($arguments[0] ?? '') !== 'articles') {
            return null;
        }
        $action = (string) ($arguments[1] ?? '');
        $slug = implode('-', array_slice($arguments, 2));
        $categoryId = null;
        if ($action === 'categorie' && $slug !== '') {
            $category = $this->articles->findCategoryBySlug($slug);
            $categoryId = $category === null ? null : (int) $category['id'];
        } elseif ($action === 'page' && $slug !== '') {
            $article = $this->articles->findPublishedBySlug($slug);
            $categoryId = $article === null || $article['category_id'] === null
                ? null
                : (int) $article['category_id'];
        }
        if ($categoryId === null || !isset($byId[$categoryId])) {
            return null;
        }

        $visited = [];
        while (isset($byId[$categoryId]) && !isset($visited[$categoryId])) {
            $visited[$categoryId] = true;
            $parentId = $byId[$categoryId]['parent_id'];
            if ($parentId === null || !isset($byId[(int) $parentId])) {
                return $categoryId;
            }
            $categoryId = (int) $parentId;
        }
        return null;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }
}
