<?php
declare(strict_types=1);

namespace PortixModule\Articles;

use Portix\Support\Translator;

final class ArticleOrganizer
{
    public function __construct(
        private readonly ArticleRepository $articles,
        private readonly Translator $translator
    ) {
    }

    public function render(): string
    {
        $categories = $this->articles->categories();
        $byId = [];
        $children = [];
        foreach ($categories as $category) {
            $id = (int) $category['id'];
            $byId[$id] = $category;
            $parent = $category['parent_id'] === null ? 0 : (int) $category['parent_id'];
            $children[$parent][] = $id;
        }

        $articlesByCategory = [];
        foreach ($this->articles->allForOrganization() as $article) {
            $group = $article['category_id'] === null ? 0 : (int) $article['category_id'];
            $articlesByCategory[$group][] = $article;
        }

        $html = '<div class="article-organizer" data-article-organizer>';
        $html .= '<section class="article-order-section"><div class="article-order-heading"><div><h2>'
            . $this->escape($this->translator->text('articles_order_categories_title')) . '</h2><p>'
            . $this->escape($this->translator->text('articles_order_categories_help'))
            . '</p></div><span class="drag-help">'
            . $this->escape($this->translator->text('articles_order_drag_help')) . '</span></div>';
        $html .= $this->mixedList('root', $children[0] ?? [], $byId, $children, $articlesByCategory, $articlesByCategory[0] ?? []);
        $html .= '</section></div>';
        return $html;
    }

    /**
     * @param list<int> $categoryIds
     * @param array<int, array<string, mixed>> $byId
     * @param array<int, list<int>> $children
     * @param array<int, list<array<string, mixed>>> $articlesByCategory
     * @param list<array<string, mixed>> $articles
     */
    private function mixedList(
        string $group,
        array $categoryIds,
        array $byId,
        array $children,
        array $articlesByCategory,
        array $articles
    ): string {
        $items = [];
        foreach ($articles as $article) {
            $items[] = [
                'type' => 'article',
                'id' => (int) $article['id'],
                'position' => (int) $article['position'],
                'label' => (string) $article['title'],
                'data' => $article,
            ];
        }
        foreach ($categoryIds as $id) {
            if (!isset($byId[$id])) {
                continue;
            }
            $category = $byId[$id];
            $items[] = [
                'type' => 'category',
                'id' => $id,
                'position' => (int) $category['position'],
                'label' => (string) $category['name'],
                'data' => $category,
            ];
        }

        /*
         * Anciennes installations : catégories et articles avaient chacun leur
         * propre numérotation. En cas de collision, on conserve l'affichage
         * historique (articles puis catégories) jusqu'au premier enregistrement,
         * qui normalise ensuite tout le niveau en une séquence unique.
         */
        $positions = [];
        $hasCollision = false;
        foreach ($items as $item) {
            $position = $item['position'];
            if (isset($positions[$position])) {
                $hasCollision = true;
                break;
            }
            $positions[$position] = true;
        }

        if ($hasCollision) {
            $categoriesFirst = $group === 'root';
            usort($items, static function (array $left, array $right) use ($categoriesFirst): int {
                if ($left['type'] !== $right['type']) {
                    if ($categoriesFirst) {
                        return $left['type'] === 'category' ? -1 : 1;
                    }
                    return $left['type'] === 'article' ? -1 : 1;
                }
                return [$left['position'], $left['label'], $left['id']]
                    <=> [$right['position'], $right['label'], $right['id']];
            });
        } else {
            usort($items, static fn (array $left, array $right): int =>
                [$left['position'], $left['type'], $left['label'], $left['id']]
                <=> [$right['position'], $right['type'], $right['label'], $right['id']]
            );
        }

        $html = '<ul class="article-order-list article-order-mixed" data-sortable-list>';
        foreach ($items as $item) {
            if ($item['type'] === 'article') {
                $html .= $this->articleItem($group, $item['data']);
                continue;
            }
            $category = $item['data'];
            $id = (int) $category['id'];
            $name = (string) $category['name'];
            $html .= '<li class="article-order-item article-order-category" draggable="true" '
                . 'data-sortable-item data-order-id="category:' . $id . '">'
                . '<input type="hidden" name="content_order[' . $this->escape($group) . '][]" value="category:'
                . $id . '"><div class="article-order-row"><span class="article-order-handle" aria-hidden="true">⋮⋮</span>'
                . '<span class="article-order-position" data-order-position></span><div class="article-order-copy"><strong>'
                . $this->escape($name) . '</strong><small>articles-categorie-'
                . $this->escape((string) $category['slug']) . '.html</small></div>'
                . $this->moveButtons($name)
                . '<a class="button button-quiet" href="Admin-articles-category-edit-' . $id . '.html">'
                . $this->escape($this->translator->text('ui_edit')) . '</a></div>';

            $html .= '<div class="article-order-contents">'
                . $this->mixedList(
                    (string) $id,
                    $children[$id] ?? [],
                    $byId,
                    $children,
                    $articlesByCategory,
                    $articlesByCategory[$id] ?? []
                )
                . '</div></li>';
        }
        if ($items === []) {
            $html .= '<li class="article-order-empty">'
                . $this->escape($this->translator->text('articles_order_no_article')) . '</li>';
        }
        return $html . '</ul>';
    }

    /** @param array<string, mixed> $article */
    private function articleItem(string $group, array $article): string
    {
        $id = (int) $article['id'];
        $title = (string) $article['title'];
        $published = (string) $article['status'] === 'published';
        return '<li class="article-order-item article-order-article" draggable="true" '
            . 'data-sortable-item data-order-id="article:' . $id . '">'
            . '<input type="hidden" name="content_order[' . $this->escape($group) . '][]" value="article:'
            . $id . '"><div class="article-order-row"><span class="article-order-handle" aria-hidden="true">⋮⋮</span>'
            . '<span class="article-order-position" data-order-position></span><div class="article-order-copy"><strong>'
            . $this->escape($title) . '</strong><small>'
            . $this->escape($this->translator->text(
                $published ? 'articles_status_published' : 'articles_status_draft'
            )) . '</small></div>'
            . $this->moveButtons($title)
            . '<a class="button button-quiet" href="Admin-articles-edit-' . $id . '.html">'
            . $this->escape($this->translator->text('ui_edit')) . '</a></div></li>';
    }

    private function moveButtons(string $title): string
    {
        return '<div class="article-order-buttons"><button class="move-button" type="button" data-move="up" aria-label="'
            . $this->escape(sprintf($this->translator->text('articles_order_move_up'), $title))
            . '">↑</button><button class="move-button" type="button" data-move="down" aria-label="'
            . $this->escape(sprintf($this->translator->text('articles_order_move_down'), $title))
            . '">↓</button></div>';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }
}
