<?php
declare(strict_types=1);

require_once './includes/class/html_sanitizer.class.php';
require_once './var/modules/articles/src/ArticleRepository.php';
require_once './var/modules/articles/src/ReadingView.php';
header('Cache-Control: no-cache, max-age=0, must-revalidate');
header('Vary: Cookie', false);
if (isset($user) && $user instanceof \Portix\Domain\UserAccount) {
    header('Cache-Control: private, no-store, max-age=0');
}

$articleRepository = new \PortixModule\Articles\ArticleRepository($database->connection());
$template->setSlot('main', './var/modules/articles/templates/index.tpl');
$template->set('articles_listing', '0');
$template->set('content_preview', '0');
$template->set('article_series_visible', '0');
$template->set('article_reading_url', '');
$template->set('article_heading_toc', '');
$template->set('article_has_related', '0');
$template->set('articles_asset_version', trim((string)file_get_contents(__DIR__ . '/MODULE-VERSION')));
$articleAction = (string) ($argument[1] ?? 'list');

if ($articleAction === 'preview') {
    $canPreview = isset($user) && is_object($user)
        && method_exists($user, 'isAdmin') && method_exists($user, 'isRedactor')
        && ($user->isAdmin() || $user->isRedactor());
    $preview = $canPreview
        ? (new \Portix\Preview\SessionPreviewStore())->get('article', (string) ($argument[2] ?? ''))
        : null;
    if ($preview === null) {
        http_response_code(404);
        $pageMetadata->noIndexNoFollow();
        $notFound = './var/templates/' . basename((string)($CONFIG['template'] ?? 'modern')) . '/not-found.tpl';
        $template->setSlot('main', is_file($notFound) ? $notFound : './var/templates/modern/not-found.tpl');
        return;
    }
    header('Cache-Control: private, no-store, max-age=0');
    $categoryId = $preview['category_id'] === null ? null : (int) $preview['category_id'];
    $category = $categoryId === null ? null : $articleRepository->findCategory($categoryId);
    $previewRendered = \PortixModule\Articles\ReadingView::document(PortixHtmlSanitizer::clean((string)$preview['body']), $translator->text('articles_table_scroll'), !empty($preview['toc_enabled']), $translator->text('articles_heading_toc'), 'articles-preview-' . (string)($argument[2] ?? '') . '.html');
    $previewRendered['body'] = \Portix\Media\ResponsiveImages::enhance($previewRendered['body'], dirname(__DIR__, 3));
    $template->set('article_heading_toc', \Portix\View\Html::trusted($previewRendered['toc']));
    $template->addRow('showarticle', [
        'title' => (string) $preview['title'],
        'category' => (string) ($category['name'] ?? ''),
        'category_slug' => (string) ($category['slug'] ?? ''),
        'body' => \Portix\View\Html::trusted($previewRendered['body']),
    ]);
    $template->set('articles_has_navigation', '0');
    $template->set('content_preview', '1');
    $pageMetadata->define(
        (string) $preview['title'],
        (string) (($preview['summary'] ?? '') ?: $preview['body']),
        '',
        'article',
        (string) ($preview['published_at'] ?? ''),
        (string) ($preview['updated_at'] ?? ''),
        (string) ($preview['author_name'] ?? ''),
        (string) (($preview['featured_image'] ?? '') ?: \Portix\Seo\PageMetadata::firstImageFromHtml((string) $preview['body'])),
        'TechArticle'
    );
    $pageMetadata->noIndexNoFollow();
    return;
}

$breadcrumbTrail->add($translator->text('articles_title'), 'articles.html');

if ($articleAction === 'page') {
    $slug = implode('-', array_slice($argument, 2));
    $article = $articleRepository->findPublishedBySlug($slug);
    if ($article === null) {
        http_response_code(404);
        $pageMetadata->noIndex();
        $notFound = './var/templates/' . basename((string)($CONFIG['template'] ?? 'modern')) . '/not-found.tpl';
        $template->setSlot('main', is_file($notFound) ? $notFound : './var/templates/modern/not-found.tpl');
        return;
    }
    // Use exactly the authorization policy enforced by ArticleAdminController.
    if (isset($user) && $user instanceof \Portix\Domain\UserAccount
        && \Portix\Domain\ArticlePermissions::allows(
            $user,
            'edit',
            $article,
            $article['category_id'] === null ? null : (int) $article['category_id'],
            $user->isAdmin() ? [] : $articleRepository->flattenedCategories()
        )) {
        $template->addRow('articleeditlink', [
            'href' => 'Admin-articles-edit-' . (int) $article['id'] . '.html',
            'label' => $translator->text('articles_public_edit', 'Modifier cet article'),
            'version' => trim((string) file_get_contents(__DIR__ . '/MODULE-VERSION')),
        ]);
    }
    $reading = $articleRepository->editorial()->reading((int)$article['id'], true);
    $rendered = \PortixModule\Articles\ReadingView::document(PortixHtmlSanitizer::clean((string)$article['body']), $translator->text('articles_table_scroll'), $reading['toc_enabled'], $translator->text('articles_heading_toc'), (string)$article['slug'] . '.html');
    $rendered['body'] = \Portix\Media\ResponsiveImages::enhance($rendered['body'], dirname(__DIR__, 3));
    $template->set('article_heading_toc', \Portix\View\Html::trusted($rendered['toc']));
    $template->set('article_has_related', $reading['related'] !== [] ? '1' : '0');
    foreach($reading['related'] as $related)$template->addRow('relatedarticle',['title'=>(string)$related['title'],'slug'=>(string)$related['slug']]);
    $template->set('article_reading_url', (string)$article['slug'] . '.html');
    $template->addRow('showarticle', [
        'title' => (string) $article['title'],
        'category' => (string) ($article['category_name'] ?? ''),
        'category_slug' => (string) ($article['category_slug'] ?? ''),
        'body' => \Portix\View\Html::trusted($rendered['body']),
    ]);
    $trail = $article['category_id'] === null
        ? []
        : $articleRepository->categoryTrail((int) $article['category_id']);
    foreach ($trail as $category) {
        $breadcrumbTrail->add(
            (string) $category['name'],
            'articles-categorie-' . (string) $category['slug'] . '.html'
        );
    }
    $pageMetadata->define(
        (string) $article['title'],
        (string) (($article['summary'] ?? '') ?: $article['body']),
        (string) $article['slug'] . '.html',
        'article',
        (string) ($article['published_at'] ?? ''),
        (string) ($article['updated_at'] ?? ''),
        (string) ($article['author_name'] ?? ''),
        (string) (($article['featured_image'] ?? '') ?: \Portix\Seo\PageMetadata::firstImageFromHtml((string) $article['body'])),
        'TechArticle'
    );

    $series = $articleRepository->editorial()->publicSeries((int) $article['id']);
    if ($series !== null) {
        $template->set('article_series_visible', '1');
        $template->set('article_series_title', (string) $series['title']);
        $template->set('article_series_description', (string) $series['description']);
        $template->set('article_series_show_title', $series['show_title'] ? '1' : '0');
        $template->set('article_series_position', sprintf($translator->text('articles_series_part'), $series['index'] + 1, count($series['parts'])));
        $template->set('article_series_position_compact', ($series['index'] + 1) . '/' . count($series['parts']));
        foreach ($series['parts'] as $part) {
            $template->addRow('seriespart', ['title'=>(string)$part['title'], 'slug'=>(string)$part['slug'], 'current'=>(int)$part['id']===(int)$article['id']?'1':'0']);
        }
        $neighbors = ['previous'=>$series['parts'][$series['index']-1] ?? null, 'next'=>$series['parts'][$series['index']+1] ?? null];
    } else {
        $neighbors = $articleRepository->publishedNeighbors(
            (int) $article['id'],
            $articleRepository->menuSettings()['navigation_mode']
        );
    }
    $template->set(
        'articles_has_navigation',
        $neighbors['previous'] === null && $neighbors['next'] === null ? '0' : '1'
    );
    if ($neighbors['previous'] !== null) {
        $template->addRow('articleprevious', [
            'title' => (string) $neighbors['previous']['title'],
            'slug' => (string) $neighbors['previous']['slug'],
        ]);
    }
    if ($neighbors['next'] !== null) {
        $template->addRow('articlenext', [
            'title' => (string) $neighbors['next']['title'],
            'slug' => (string) $neighbors['next']['slug'],
        ]);
    }
    return;
}

if (!in_array($articleAction, ['list', 'categorie'], true)) {
    http_response_code(404);
    $pageMetadata->noIndex();
    $notFound = './var/templates/' . basename((string)($CONFIG['template'] ?? 'modern')) . '/not-found.tpl';
        $template->setSlot('main', is_file($notFound) ? $notFound : './var/templates/modern/not-found.tpl');
    return;
}

$selectedCategory = null;
if ($articleAction === 'categorie') {
    $slug = implode('-', array_slice($argument, 2));
    $selectedCategory = $articleRepository->findCategoryBySlug($slug);
    if ($selectedCategory === null) {
        http_response_code(404);
        $pageMetadata->noIndex();
        $notFound = './var/templates/' . basename((string)($CONFIG['template'] ?? 'modern')) . '/not-found.tpl';
        $template->setSlot('main', is_file($notFound) ? $notFound : './var/templates/modern/not-found.tpl');
        return;
    }
}

if ($selectedCategory !== null) {
    foreach ($articleRepository->categoryTrail((int) $selectedCategory['id']) as $category) {
        $breadcrumbTrail->add(
            (string) $category['name'],
            'articles-categorie-' . (string) $category['slug'] . '.html'
        );
    }
}

$template->set(
    'articles_list_title',
    $selectedCategory === null ? $translator->text('articles_title') : (string) $selectedCategory['name']
);
$pageMetadata->define(
    $selectedCategory === null ? $translator->text('articles_title') : (string) $selectedCategory['name'],
    $selectedCategory === null ? '' : (string) ($selectedCategory['description'] ?? ''),
    $selectedCategory === null
        ? 'articles.html'
        : 'articles-categorie-' . (string) $selectedCategory['slug'] . '.html'
);
$template->set('articles_category_description_value', (string) ($selectedCategory['description'] ?? ''));
$template->set('articles_listing', '1');
$includeHidden = $selectedCategory !== null
    && !$articleRepository->isCategoryVisibleOnSite((int) $selectedCategory['id']);
$outline = $articleRepository->publicOutline(
    $selectedCategory === null ? null : (int) $selectedCategory['id'],
    $includeHidden
);
[$tocHtml, $articleCount] = portixArticlesRenderToc(
    $outline,
    $selectedCategory,
    $translator->text('articles_uncategorized')
);
$template->set('articles_toc', \Portix\View\Html::trusted($tocHtml));
$template->set('articles_has_items', $articleCount > 0 ? '1' : '0');
if ($articleCount === 0) {
    $pageMetadata->noIndex();
}

/**
 * @param array{items:list<array<string,mixed>>} $outline
 * @param array<string,mixed>|null $selectedCategory
 * @return array{0:string,1:int}
 */
function portixArticlesRenderToc(array $outline, ?array $selectedCategory, string $uncategorizedLabel): array
{
    $escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $html = '<div class="article-toc' . ($selectedCategory === null ? ' article-toc-columns' : '') . '">';
    $articleCount = 0;

    $render = function (array $items, int $categoryDepth = 0) use (&$render, &$articleCount, $escape): string {
        $output = '';
        $articleListOpen = false;
        $closeArticleList = static function () use (&$output, &$articleListOpen): void {
            if ($articleListOpen) {
                $output .= '</ul>';
                $articleListOpen = false;
            }
        };

        foreach ($items as $item) {
            if (($item['type'] ?? '') === 'article' && is_array($item['article'] ?? null)) {
                if (!$articleListOpen) {
                    $output .= '<ul>';
                    $articleListOpen = true;
                }
                $article = $item['article'];
                $articleCount++;
                $output .= '<li><a href="' . $escape((string) $article['slug']) . '.html">'
                    . $escape((string) $article['title']) . '</a></li>';
                continue;
            }

            if (($item['type'] ?? '') !== 'category' || !is_array($item['category'] ?? null)) {
                continue;
            }
            $closeArticleList();
            $category = $item['category'];
            $level = min(6, 2 + $categoryDepth);
            $output .= '<section class="article-category">';
            $output .= '<h' . $level . '><a href="articles-categorie-'
                . $escape((string) $category['slug']) . '.html">'
                . $escape((string) $category['name']) . '</a></h' . $level . '>';
            if (trim((string) ($category['description'] ?? '')) !== '') {
                $output .= '<p class="article-category-description">' . $escape((string) $category['description']) . '</p>';
            }
            $children = is_array($item['items'] ?? null) ? $item['items'] : [];
            if ($children !== []) {
                $output .= '<div class="article-category-children">' . $render($children, $categoryDepth + 1) . '</div>';
            }
            $output .= '</section>';
        }
        $closeArticleList();
        return $output;
    };

    $html .= $render(is_array($outline['items'] ?? null) ? $outline['items'] : []);
    $html .= '</div>';
    return [$html, $articleCount];
}
