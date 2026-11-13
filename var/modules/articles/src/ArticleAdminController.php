<?php
declare(strict_types=1);

namespace PortixModule\Articles;

use Portix\Domain\UserAccount;
use Portix\Support\Slugger;
use Portix\Support\Translator;
use Portix\Repository\ContentSafetyRepository;
use RuntimeException;
use Throwable;

require_once __DIR__ . '/SeriesAdminController.php';

final class ArticleAdminController
{
    private ?array $permissionCategories = null;

    /** @param list<string> $reservedSlugs */
    public function __construct(
        private readonly ArticleRepository $articles,
        private readonly string $root,
        private readonly Translator $translator,
        private readonly array $reservedSlugs = [],
        private readonly ?ContentSafetyRepository $safety = null
    ) {
    }

    /** @param list<string> $arguments */
    public function handle(\Portix\View\Template $template, array $arguments, UserAccount $user): void
    {
        $this->permissionCategories = null;
        require_once $this->root . '/includes/class/html_sanitizer.class.php';
        require_once $this->root . '/includes/class/image_storage.class.php';

        $template->set('articles_asset_version', trim((string)file_get_contents($this->root . '/var/modules/articles/MODULE-VERSION')));
        $action = (string) ($arguments[2] ?? 'list');
        if (!$user->isAdmin() && !$user->isRedactor()) { $this->deny($template); return; }
        if (!$user->isAdmin() && in_array($action, ['order', 'category', 'categories', 'menu', 'series'], true)) { $this->deny($template); return; }
        $targetArticle = isset($arguments[3]) && ctype_digit((string)$arguments[3]) ? $this->articles->find((int)$arguments[3]) : null;
        if ($action === 'autosave') {
            $this->autosaveArticle($arguments, $user);
        }
        if (in_array($action, ['edit','preview','status','delete','new'], true)) {
            $targetCategory = $targetArticle === null || $targetArticle['category_id'] === null ? null : (int)$targetArticle['category_id'];
            if ($targetArticle === null && in_array($action, ['new','preview'], true) && !$user->isAdmin()) {
                $hasCategory = $user->articlePermissions()['all_categories'];
                foreach ($this->articles->flattenedCategories() as $category) {
                    $hasCategory = $hasCategory || $this->categoryAllowed($user, (int)$category['id']);
                }
                if (!$user->articlePermissions()['create'] || !$hasCategory) { $this->deny($template); return; }
            } elseif (!$this->allowed($user, $action === 'delete' ? 'delete' : 'edit', $targetArticle, $targetCategory)) {
                $this->deny($template); return;
            }
        }

        $template->set('articles_can_configure_menu', $user->isAdmin() ? '1' : '0');
        if ($action === 'upload' && ($arguments[3] ?? '') === 'image') {
            $this->uploadImage($user);
        }
        if ($action === 'delete') {
            $this->deleteArticle($arguments, $user);
        }
        if ($action === 'status') {
            $this->changeArticleStatus($arguments, $user);
        }
        if ($action === 'preview') {
            $this->previewArticle($arguments, $user);
        }
        if ($action === 'new' || $action === 'edit') {
            $this->editArticle($template, $arguments, $user, $action === 'new', $targetArticle);
            return;
        }
        if ($action === 'series') {
            (new SeriesAdminController($this->articles, $this->translator))->handle($template, $arguments);
            return;
        }
        if ($action === 'menu') {
            if (!$user->isAdmin()) {
                http_response_code(403);
                $template->setSlot('main', './var/admin/templates/access-denied.tpl');
                return;
            }
            $this->menuSettings($template);
            return;
        }
        if ($action === 'order') {
            $this->ordering($template);
            return;
        }
        if ($action === 'category') {
            $this->handleCategory($template, $arguments, $user);
            return;
        }
        if ($action === 'categories') {
            $this->categoryListing($template, $user);
            return;
        }
        $this->listing($template, $user);
    }

    private function listing(\Portix\View\Template $template, UserAccount $user): void
    {
        $template->setSlot('main', './var/modules/articles/templates/admin-list.tpl');
        $visibleRows = array_values(array_filter(
            $this->articles->allForAdministration(),
            fn (array $row): bool => $this->allowed($user, 'edit', $row, $row['category_id'] === null ? null : (int)$row['category_id'])
        ));
        $template->set('articles_total', (string) count($visibleRows));
        $template->set('articles_published', (string) count(array_filter($visibleRows, static fn (array $row): bool => $row['status'] === 'published')));
        $pendingCount = count(array_filter($visibleRows, static fn (array $row): bool => $row['status'] === 'pending'));
        $template->set('review_pending_count', (string) $pendingCount);
        $template->set('articles_review_admin_shortcut', $user->isAdmin() && $pendingCount > 0 ? '1' : '0');
        $template->set('review_has_rows', $visibleRows === [] ? '0' : '1');
        $template->set('review_empty_help', $this->translator->text($user->isAdmin() ? 'articles_empty_admin' : 'articles_empty_redactor'));
        $template->set('articles_can_create', $user->isAdmin() || $user->articlePermissions()['create'] ? '1' : '0');

        // Search acts only on articles already visible to this account.
        $rawState = $_GET['state'] ?? '';
        $state = is_string($rawState) && in_array($rawState, ['draft', 'pending', 'published'], true) ? $rawState : '';
        $rawQuery = $_GET['q'] ?? '';
        $query = is_string($rawQuery) ? trim(mb_substr($rawQuery, 0, 120, 'UTF-8')) : '';
        require_once __DIR__ . '/ArticleListFilter.php';
        $visibleRows = ArticleListFilter::apply($visibleRows, $state, $query);
        $template->set('articles_filter_query', $query);
        $template->set('articles_filter_all_selected', $state === '' ? 'selected' : '');
        foreach (['draft', 'pending', 'published'] as $choice) {
            $template->set('articles_filter_' . $choice . '_selected', $state === $choice ? 'selected' : '');
        }
        $template->set('articles_filter_count', (string) count($visibleRows));
        $template->set('articles_filter_no_matches', $visibleRows === [] ? '1' : '0');
        $template->set('articles_filter_active', $state !== '' || $query !== '' ? '1' : '0');

        require_once __DIR__ . '/ArticleListPagination.php';
        $rawPage = $_GET['page'] ?? '1';
        $requestedPage = is_string($rawPage) && ctype_digit($rawPage)
            ? (int) min((float) $rawPage, 1000000000)
            : 1;
        $pagination = ArticleListPagination::paginate($visibleRows, $requestedPage);
        $visibleRows = $pagination['rows'];
        $template->set('articles_page_current', (string) $pagination['page']);
        $template->set('articles_page_total', (string) $pagination['pages']);
        $template->set('articles_page_from', (string) $pagination['from']);
        $template->set('articles_page_to', (string) $pagination['to']);
        $template->set('articles_pages_visible', $pagination['pages'] > 1 ? '1' : '0');
        $template->set('articles_page_previous_visible', $pagination['page'] > 1 ? '1' : '0');
        $template->set('articles_page_next_visible', $pagination['page'] < $pagination['pages'] ? '1' : '0');
        $template->set('articles_page_previous_url', ArticleListPagination::pageUrl(max(1, $pagination['page'] - 1), $state, $query));
        $template->set('articles_page_next_url', ArticleListPagination::pageUrl(min($pagination['pages'], $pagination['page'] + 1), $state, $query));


        foreach ($visibleRows as $row) {
            $status = (string) $row['status'];
            $published = $status === 'published';
            $pending = $status === 'pending';
            $categoryId = $row['category_id'] === null ? null : (int) $row['category_id'];
            $canPublish = $this->allowed($user, 'publish', $row, $categoryId);
            $canEdit = $this->allowed($user, 'edit', $row, $categoryId);
            $canAct = false;
            $next = '';
            $action = '';
            if ($published && $canPublish) {
                $canAct = true; $next = 'draft'; $action = 'articles_make_draft';
            } elseif ($pending && $canPublish && $user->isAdmin()) {
                $canAct = true; $next = 'published'; $action = 'articles_review_approve';
            } elseif (!$pending && !$published && $canPublish) {
                $canAct = true; $next = 'published'; $action = 'articles_publish';
            } elseif (!$pending && !$published && $canEdit) {
                $canAct = true; $next = 'pending'; $action = 'articles_review_request';
            }
            $template->addRow('articlerow', [
                'id' => (string) $row['id'],
                'title' => (string) $row['title'],
                'slug' => (string) $row['slug'],
                'category' => (string) ($row['category_name'] ?: $this->translator->text('articles_uncategorized')),
                'status' => $this->translator->text('articles_status_' . $status, $status),
                'status_class' => $pending ? 'state-pending' : ($published ? 'state-enabled' : 'state-draft'),
                'row_class' => $pending ? 'article-row-pending' : '',
                'date' => (string) $row['updated_at'],
                'public_url' => $published ? (string) $row['slug'] . '.html' : '',
                'can_delete' => $this->allowed($user, 'delete', $row, $categoryId) ? '1' : '0',
                'can_publish' => $canAct ? '1' : '0',
                'next_status' => $next,
                'status_action' => $action !== '' ? $this->translator->text($action) : '',
                'status_action_class' => $next === 'draft' ? 'button-make-draft' : 'button-publish',
                'can_send_back' => $pending && $user->isAdmin() ? '1' : '0',
                'review_note' => (string) ($row['review_note'] ?? ''),
            ]);
        }
    }

    private function menuSettings(\Portix\View\Template $template): void
    {
        $message = '';
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            try {
                \PortixSecurity::requirePostWithCsrf();
                $this->articles->saveMenuSettings(
                    isset($_POST['header_enabled']),
                    isset($_POST['show_articles']),
                    is_scalar($_POST['navigation_mode'] ?? null)
                        ? (string) $_POST['navigation_mode']
                        : ''
                );
                $message = $this->translator->text('articles_menu_saved');
            } catch (RuntimeException $error) {
                if ($error->getMessage() === 'security_access_denied') { http_response_code(403); }
                $message = $this->translator->exception($error);
            }
        }
        $settings = $this->articles->menuSettings();
        $template->setSlot('main', './var/modules/articles/templates/admin-menu.tpl');
        $template->set('articles_menu_message', $message);
        $template->set('articles_menu_header_checked', $settings['header_enabled'] ? 'checked' : '');
        $template->set('articles_menu_articles_checked', $settings['show_articles'] ? 'checked' : '');
        foreach (['off', 'category', 'book'] as $mode) {
            $template->set(
                'articles_menu_navigation_' . $mode . '_selected',
                $settings['navigation_mode'] === $mode ? 'selected' : ''
            );
        }
    }

    private function ordering(\Portix\View\Template $template): void
    {
        $message = '';
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            try {
                \PortixSecurity::requirePostWithCsrf();
                $this->articles->reorderContent(
                    $this->postedMixedOrderGroups($_POST['content_order'] ?? [])
                );
                $message = $this->translator->text('articles_order_saved');
            } catch (RuntimeException $error) {
                $message = $this->translator->exception($error);
            }
        }

        $organizer = new ArticleOrganizer($this->articles, $this->translator);
        $template->setSlot('main', './var/modules/articles/templates/admin-order.tpl');
        $template->set('articles_order_message', $message);
        $template->set('articles_order_tree', \Portix\View\Html::trusted($organizer->render()));
    }

    /** @param list<string> $arguments */
    private function editArticle(\Portix\View\Template $template, array $arguments, UserAccount $user, bool $isNew, ?array $targetArticle): void
    {
        $id = $isNew ? null : $this->numericId($arguments[3] ?? null);
        $existing = $isNew ? null : $targetArticle;
        if (!$isNew && $existing === null) {
            throw new RuntimeException('articles_not_found');
        }
        $draft = $existing ?? [
            'category_id' => null,
            'title' => '',
            'slug' => '',
            'body' => '',
            'status' => 'draft',
            'published_at' => null,
            'author_name' => '',
            'featured_image' => '',
            'summary' => '',
        ];
        $reading = $id === null ? ['toc_enabled'=>false,'related'=>[]] : $this->articles->editorial()->reading($id);
        $tocEnabled = $reading['toc_enabled'];
        $relatedIds = array_map(static fn(array $a): int=>(int)$a['id'], $reading['related']);
        $eligible = null;
        $message = '';

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            try {
                \PortixSecurity::requirePostWithCsrf();
                $title = trim(strip_tags((string) ($_POST['title'] ?? '')));
                $length = function_exists('mb_strlen') ? mb_strlen($title, 'UTF-8') : strlen($title);
                if ($title === '' || $length > 250) {
                    throw new RuntimeException('articles_invalid_title');
                }
                $categoryId = $this->optionalNumericId($_POST['category_id'] ?? null);
                $requestedStatus = is_scalar($_POST['status'] ?? null) ? (string) $_POST['status'] : '';
                if (!in_array($requestedStatus, ['draft', 'published'], true)) {
                    throw new RuntimeException('articles_invalid_status');
                }
                // Changes to an article awaiting review require a new request.
                $status = ($existing['status'] ?? '') === 'pending' ? 'draft' : $requestedStatus;
                if (!$this->allowed($user, 'edit', $existing, $categoryId)
                    || (($status === 'published' || ($existing['status'] ?? '') === 'published') && !$this->allowed($user, 'publish', $existing, $categoryId))) {
                    throw new RuntimeException('security_access_denied');
                }
                $slugSource = trim((string) ($_POST['slug'] ?? ''));
                $slug = $this->articles->uniqueArticleSlug(
                    Slugger::fromText($slugSource !== '' ? $slugSource : $title, 'article'),
                    $this->reservedSlugs,
                    $id
                );
                $body = \PortixHtmlSanitizer::clean($_POST['content'] ?? '');
                $body = \PortixImageStorage::optimiseReferencedFilenames(
                    $body,
                    $this->root . '/var/uploads/images'
                );
                $authorName = trim(strip_tags((string) ($_POST['author_name'] ?? '')));
                $featuredImage = trim((string) ($_POST['featured_image'] ?? ''));
                $summary = trim(strip_tags((string) ($_POST['summary'] ?? '')));
                $summaryLength = function_exists('mb_strlen') ? mb_strlen($summary, 'UTF-8') : strlen($summary);
                if ($summaryLength > 170) {
                    throw new RuntimeException('articles_invalid_summary');
                }
                if ((function_exists('mb_strlen') ? mb_strlen($authorName, 'UTF-8') : strlen($authorName)) > 160
                    || strlen($featuredImage) > 500) {
                    throw new RuntimeException('articles_invalid_seo_fields');
                }
                $now = gmdate('Y-m-d H:i:s');
                $publishedAt = $status === 'published'
                    ? ((string) ($existing['published_at'] ?? '') ?: $now)
                    : null;

                $saveReading = isset($_POST['reading_settings_present']);
                if($saveReading){
                    $eligible = $this->eligibleRelatedArticles($user, $id);
                    $tocEnabled=isset($_POST['toc_enabled']);
                    $raw=$_POST['related']??[];$positions=$_POST['related_positions']??[];
                    if(!is_array($raw)||!is_array($positions))throw new RuntimeException('articles_related_invalid');
                    $submitted=[];
                    foreach($raw as $value){
                        if(!is_scalar($value)||!ctype_digit((string)$value)||!isset($eligible[(int)$value]))throw new RuntimeException('articles_related_invalid');
                        $position=$positions[(int)$value]??0;if(!is_scalar($position)||!ctype_digit((string)$position))throw new RuntimeException('articles_invalid_order');
                        $submitted[]=(int)$value;
                    }
                    usort($submitted,static fn(int $a,int $b): int=>(int)($positions[$a]??0)<=>(int)($positions[$b]??0));
                    $relatedIds=array_merge($submitted,array_values(array_filter($relatedIds,static fn(int $target):bool=>!isset($eligible[$target]))));
                }
                $this->articles->transaction(function () use ($id,$categoryId,$title,$slug,$body,$status,$user,$publishedAt,$now,$authorName,$featuredImage,$summary,$existing,$saveReading,$tocEnabled,$relatedIds): void {
                if ($id === null) {
                    $id = $this->articles->createArticle(
                        $categoryId,
                        $title,
                        $slug,
                        $body,
                        $status,
                        $user->pseudo(),
                        $publishedAt,
                        $now,
                        $authorName,
                        $featuredImage,
                        $summary
                    );
                } else {
                    if ($this->safety !== null && $existing !== null) {
                        $this->safety->captureRevision('article', $id, $existing, $user->pseudo());
                    }
                    $this->articles->updateArticle(
                        $id,
                        $categoryId,
                        $title,
                        $slug,
                        $body,
                        $status,
                        $publishedAt,
                        $now,
                        $authorName,
                        $featuredImage,
                        $summary
                    );
                }
                if ($existing !== null) {
                    $this->articles->saveReviewNote($id, null);
                }
                if($saveReading)$this->articles->editorial()->saveReading($id,$tocEnabled,$relatedIds);
                });
                // Saving the article replaces its private recovery copy.
                $this->articles->autosaves()->delete($user->pseudo(), $id ?? 0);
                header('Location: Admin-articles.html');
                exit;
            } catch (RuntimeException $error) {
                $message = $this->translator->exception($error);
                $draft = [
                    'category_id' => $this->optionalNumericId($_POST['category_id'] ?? null),
                    'title' => (string) ($_POST['title'] ?? ''),
                    'slug' => (string) ($_POST['slug'] ?? ''),
                    'body' => \PortixHtmlSanitizer::clean($_POST['content'] ?? ''),
                    'status' => ($_POST['status'] ?? '') === 'published' ? 'published' : 'draft',
                    'published_at' => $existing['published_at'] ?? null,
                    'author_name' => trim(strip_tags((string) ($_POST['author_name'] ?? ''))),
                    'featured_image' => trim((string) ($_POST['featured_image'] ?? '')),
                    'summary' => trim(strip_tags((string) ($_POST['summary'] ?? ''))),
                ];
            }
        }

        $autosaved = $this->articles->autosaves()->load($user->pseudo(), $id ?? 0);
        // Do not offer a recovery copy based on an older saved revision.
        if ($autosaved !== null && $existing !== null
            && !hash_equals((string) $existing['updated_at'], $autosaved['base_updated_at'])) {
            $autosaved = null;
        }
        $template->set('articles_autosave_url', 'Admin-articles-autosave'
            . ($id === null ? '' : '-' . $id) . '.html');
        $template->set('articles_autosave_base', (string) ($existing['updated_at'] ?? ''));
        $template->set('articles_autosave_snapshot', $autosaved === null ? ''
            : json_encode($autosaved, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE));
        $template->setSlot('main', './var/modules/articles/templates/admin-form.tpl');
        $template->set('articles_can_publish', $user->isAdmin() || $user->articlePermissions()['publish'] ? '1' : '0');
        $template->set('review_required', !$user->isAdmin() && !$user->articlePermissions()['publish'] ? '1' : '0');
        $template->set('review_is_pending', ($existing['status'] ?? '') === 'pending' ? '1' : '0');
        $template->set('review_note_value', (string) ($existing['review_note'] ?? ''));
        $template->set('articles_form_title', $this->translator->text(
            $isNew ? 'articles_form_new' : 'articles_form_edit'
        ));
        $template->set('articles_form_action', $isNew
            ? 'Admin-articles-new.html'
            : 'Admin-articles-edit-' . $id . '.html');
        $template->set('articles_message', $message);
        $template->set('articles_title_value', (string) $draft['title']);
        $template->set('articles_slug_value', (string) $draft['slug']);
        $template->set('articles_body_value', (string) $draft['body']);
        $template->set('article_toc_checked', $tocEnabled ? 'checked' : '');
        $categoryId = $draft['category_id'] === null ? null : (int)$draft['category_id'];
        $selectedPositions = array_flip($relatedIds);
        $eligible ??= $this->eligibleRelatedArticles($user, $id);
        usort($eligible, static function (array $a, array $b) use ($selectedPositions, $categoryId): int {
            $aSelected = isset($selectedPositions[(int)$a['id']]);
            $bSelected = isset($selectedPositions[(int)$b['id']]);
            if ($aSelected !== $bSelected) { return $aSelected ? -1 : 1; }
            if ($aSelected) { return $selectedPositions[(int)$a['id']] <=> $selectedPositions[(int)$b['id']]; }
            $aSame = $categoryId !== null && (int)($a['category_id'] ?? 0) === $categoryId;
            $bSame = $categoryId !== null && (int)($b['category_id'] ?? 0) === $categoryId;
            if ($aSame !== $bSame) { return $aSame ? -1 : 1; }
            return strnatcasecmp((string)$a['title'], (string)$b['title']) ?: ((int)$a['id'] <=> (int)$b['id']);
        });
        foreach($eligible as $i=>$target)$template->addRow('relationoption',[
            'id'=>(string)$target['id'], 'title'=>(string)$target['title'],
            'category_id'=>(string)($target['category_id'] ?? ''), 'category'=>(string)($target['category_name'] ?: $this->translator->text('articles_uncategorized')),
            'status'=>$this->translator->text('articles_status_'.$target['status']),
            'checked'=>isset($selectedPositions[(int)$target['id']])?'checked':'', 'position'=>(string)$i,
        ]);
        $template->set('articles_author_value', (string) ($draft['author_name'] ?? ''));
        $template->set('articles_featured_image_value', (string) ($draft['featured_image'] ?? ''));
        $template->set('articles_summary_value', (string) ($draft['summary'] ?? ''));
        $template->set('articles_field_summary', $this->translator->text('articles_field_summary'));
        $template->set('articles_field_summary_help', $this->translator->text('articles_field_summary_help'));
        $template->set('articles_preview_action', $id === null
            ? 'Admin-articles-preview.html'
            : 'Admin-articles-preview-' . $id . '.html');
        $template->set('articles_draft_selected', $draft['status'] === 'draft' ? 'selected' : '');
        $template->set('articles_published_selected', $draft['status'] === 'published' ? 'selected' : '');
        if ($this->categoryAllowed($user, null)) {
        $template->addRow('categoryoption', [
            'id' => '',
            'name' => $this->translator->text('articles_uncategorized'),
            'selected' => $draft['category_id'] === null ? 'selected' : '',
        ]);
        }
        foreach ($this->articles->flattenedCategories() as $category) {
            if (!$this->categoryAllowed($user, (int)$category['id'])) { continue; }
            $template->addRow('categoryoption', [
                'id' => (string) $category['id'],
                'name' => (string) $category['indented_name'],
                'selected' => (int) ($draft['category_id'] ?? 0) === (int) $category['id'] ? 'selected' : '',
            ]);
        }
    }

    /** @param list<string> $arguments */
    private function previewArticle(array $arguments, UserAccount $user): never
    {
        \PortixSecurity::requirePostWithCsrf();
        $id = isset($arguments[3]) ? $this->numericId($arguments[3]) : null;
        $existing = $id === null ? null : $this->articles->find($id);
        if ($id !== null && $existing === null) {
            throw new RuntimeException('articles_not_found');
        }

        if (array_key_exists('title', $_POST)) {
            $title = trim(strip_tags((string) ($_POST['title'] ?? '')));
            $categoryId = $this->optionalNumericId($_POST['category_id'] ?? null);
            $body = \PortixHtmlSanitizer::clean($_POST['content'] ?? '');
            $authorName = trim(strip_tags((string) ($_POST['author_name'] ?? '')));
            $featuredImage = trim((string) ($_POST['featured_image'] ?? ''));
            $summary = trim(strip_tags((string) ($_POST['summary'] ?? '')));
            $slug = trim((string) ($_POST['slug'] ?? ''));
        } elseif ($existing !== null) {
            $title = (string) $existing['title'];
            $categoryId = $existing['category_id'] === null ? null : (int) $existing['category_id'];
            $body = (string) $existing['body'];
            $authorName = (string) ($existing['author_name'] ?? '');
            $featuredImage = (string) ($existing['featured_image'] ?? '');
            $summary = (string) ($existing['summary'] ?? '');
            $slug = (string) $existing['slug'];
        } else {
            $title = '';
            $categoryId = null;
            $body = '';
            $authorName = '';
            $featuredImage = '';
            $summary = '';
            $slug = '';
        }
        if (!$this->allowed($user, 'edit', $existing, $categoryId)) { http_response_code(403); throw new RuntimeException('security_access_denied'); }
        if ($title === '') {
            $title = $this->translator->text('content_preview_untitled');
        }
        $now = gmdate('Y-m-d H:i:s');
        $token = (new \Portix\Preview\SessionPreviewStore())->create('article', [
            'title' => $title,
            'slug' => $slug,
            'category_id' => $categoryId,
            'body' => $body,
            'toc_enabled' => array_key_exists('reading_settings_present', $_POST) ? isset($_POST['toc_enabled']) : ($id === null ? false : $this->articles->editorial()->reading($id)['toc_enabled']),
            'summary' => $summary,
            'author_name' => $authorName,
            'featured_image' => $featuredImage,
            'created_by' => (string) ($existing['created_by'] ?? $user->pseudo()),
            'published_at' => (string) ($existing['published_at'] ?? $now),
            'updated_at' => $now,
        ]);
        header('Location: articles-preview-' . $token . '.html');
        exit;
    }

    /** @param list<string> $arguments */
    private function deleteArticle(array $arguments, UserAccount $user): never
    {
        \PortixSecurity::requirePostWithCsrf();
        $id = $this->numericId($arguments[3] ?? null);
        $existing = $this->articles->find($id);
        if ($existing === null) {
            throw new RuntimeException('articles_not_found');
        }
        if (!$this->allowed($user, 'delete', $existing, $existing['category_id'] === null ? null : (int)$existing['category_id'])) { throw new RuntimeException('security_access_denied'); }
        if ($this->safety !== null) {
            $this->safety->moveToTrash('article', $id, (string) $existing['title'], $existing, $user->pseudo());
        }
        $this->articles->deleteArticle($id);
        header('Location: Admin-articles.html');
        exit;
    }

    /** @param list<string> $arguments */
    private function changeArticleStatus(array $arguments, UserAccount $user): never
    {
        \PortixSecurity::requirePostWithCsrf();
        $status = is_scalar($_POST['status'] ?? null) ? (string) $_POST['status'] : '';
        $id = $this->numericId($arguments[3] ?? null);
        $existing = $this->articles->find($id);
        if ($existing === null) { throw new RuntimeException('articles_not_found'); }
        $current = (string) $existing['status'];
        $categoryId = $existing['category_id'] === null ? null : (int) $existing['category_id'];
        $canPublish = $this->allowed($user, 'publish', $existing, $categoryId);
        $canEdit = $this->allowed($user, 'edit', $existing, $categoryId);
        require_once __DIR__ . '/ArticleReviewPolicy.php';
        $note = ArticleReviewPolicy::transition(
            $current, $status, $user->isAdmin(), $canEdit, $canPublish,
            (string) ($_POST['review_note'] ?? '')
        );

        $this->articles->transaction(function () use ($id, $existing, $user, $status, $note): void {
            if ($this->safety !== null) {
                $this->safety->captureRevision('article', $id, $existing, $user->pseudo());
            }
            $this->articles->updateArticleStatus($id, $status, gmdate('Y-m-d H:i:s'));
            $this->articles->saveReviewNote($id, $note);
        });
        header('Location: Admin-articles.html');
        exit;
    }

    /** @param list<string> $arguments */
    private function handleCategory(\Portix\View\Template $template, array $arguments, UserAccount $user): void
    {
        $categoryAction = (string) ($arguments[3] ?? 'list');
        if ($categoryAction === 'delete') {
            if (!$user->isAdmin()) {
                throw new RuntimeException('articles_delete_forbidden');
            }
            \PortixSecurity::requirePostWithCsrf();
            $id = $this->numericId($arguments[4] ?? null);
            $existing = $this->articles->findCategory($id);
            if ($existing === null) { throw new RuntimeException('articles_category_not_found'); }
            if ($this->safety !== null) {
                $snapshot = $this->safety->categorySnapshot($id);
                $this->safety->moveToTrash('article_category', $id, (string) $existing['name'], $snapshot, $user->pseudo());
            }
            $this->articles->deleteCategory($id);
            header('Location: Admin-articles-categories.html');
            exit;
        }
        if ($categoryAction === 'new' || $categoryAction === 'edit') {
            $this->editCategory($template, $arguments, $categoryAction === 'new');
            return;
        }
        $this->categoryListing($template, $user);
    }

    private function categoryListing(\Portix\View\Template $template, UserAccount $user): void
    {
        $template->setSlot('main', './var/modules/articles/templates/admin-categories.tpl');
        foreach ($this->articles->flattenedCategories() as $category) {
            $visible = $this->articles->isCategoryVisibleOnSite((int) $category['id']);
            $template->addRow('categoryrow', [
                'id' => (string) $category['id'],
                'name' => (string) $category['indented_name'],
                'slug' => (string) $category['slug'],
                'count' => (string) $category['article_count'],
                'can_delete' => $user->isAdmin() ? '1' : '0',
                'is_visible' => $visible ? '1' : '0',
                'visibility' => $this->translator->text(
                    $visible ? 'articles_category_shown' : 'articles_category_hidden'
                ),
                'visibility_class' => $visible ? 'state-enabled' : 'state-draft',
            ]);
        }
    }

    /** @param list<string> $arguments */
    private function editCategory(\Portix\View\Template $template, array $arguments, bool $isNew): void
    {
        $id = $isNew ? null : $this->numericId($arguments[4] ?? null);
        $existing = $id === null ? null : $this->articles->findCategory($id);
        if (!$isNew && $existing === null) {
            throw new RuntimeException('articles_category_not_found');
        }
        $draft = $existing ?? ['name' => '', 'slug' => '', 'parent_id' => null, 'is_visible' => 1, 'description' => ''];
        $message = '';

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            try {
                \PortixSecurity::requirePostWithCsrf();
                $name = trim(strip_tags((string) ($_POST['name'] ?? '')));
                $length = function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name);
                if ($name === '' || $length > 150) {
                    throw new RuntimeException('articles_category_invalid_name');
                }
                $slugSource = trim((string) ($_POST['slug'] ?? ''));
                $slug = $this->articles->uniqueCategorySlug(
                    Slugger::fromText($slugSource !== '' ? $slugSource : $name, 'categorie'),
                    $id
                );
                $parentId = $this->optionalNumericId($_POST['parent_id'] ?? null);
                $isVisible = isset($_POST['is_visible']);
                $description = trim(strip_tags((string) ($_POST['description'] ?? '')));
                $descriptionLength = function_exists('mb_strlen')
                    ? mb_strlen($description, 'UTF-8')
                    : strlen($description);
                if ($descriptionLength > 170) {
                    throw new RuntimeException('articles_category_invalid_description');
                }
                if ($id === null) {
                    $this->articles->createCategory($name, $slug, $parentId, $isVisible, $description);
                } else {
                    $this->articles->updateCategory($id, $name, $slug, $parentId, $isVisible, $description);
                }
                header('Location: Admin-articles-categories.html');
                exit;
            } catch (RuntimeException $error) {
                $message = $this->translator->exception($error);
                $draft = [
                    'name' => (string) ($_POST['name'] ?? ''),
                    'slug' => (string) ($_POST['slug'] ?? ''),
                    'parent_id' => $this->optionalNumericId($_POST['parent_id'] ?? null),
                    'is_visible' => isset($_POST['is_visible']) ? 1 : 0,
                    'description' => trim(strip_tags((string) ($_POST['description'] ?? ''))),
                ];
            }
        }

        $template->setSlot('main', './var/modules/articles/templates/admin-category-form.tpl');
        $template->set('articles_category_form_title', $this->translator->text(
            $isNew ? 'articles_category_new' : 'articles_category_edit'
        ));
        $template->set('articles_category_form_action', $isNew
            ? 'Admin-articles-category-new.html'
            : 'Admin-articles-category-edit-' . $id . '.html');
        $template->set('articles_message', $message);
        $template->set('articles_category_name_value', (string) $draft['name']);
        $template->set('articles_category_slug_value', (string) $draft['slug']);
        $template->set('articles_category_visible_checked', !empty($draft['is_visible']) ? 'checked' : '');
        $template->set('articles_category_description', $this->translator->text('articles_category_description'));
        $template->set(
            'articles_category_description_help',
            $this->translator->text('articles_category_description_help')
        );
        $template->set('articles_category_description_value', (string) ($draft['description'] ?? ''));
        $template->addRow('parentoption', [
            'id' => '',
            'name' => $this->translator->text('articles_category_no_parent'),
            'selected' => $draft['parent_id'] === null ? 'selected' : '',
        ]);
        foreach ($this->articles->flattenedCategories($id) as $category) {
            $template->addRow('parentoption', [
                'id' => (string) $category['id'],
                'name' => (string) $category['indented_name'],
                'selected' => (int) ($draft['parent_id'] ?? 0) === (int) $category['id'] ? 'selected' : '',
            ]);
        }
    }

    /**
     * Stores a private recovery snapshot. It never creates, edits or publishes an article.
     * The manual save remains the only way to update public content.
     * @param list<string> $arguments
     */
    private function autosaveArticle(array $arguments, UserAccount $user): never
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !\PortixSecurity::csrfIsValid()) {
            $this->json(403, ['ok' => false, 'error' => 'Requête refusée.']);
        }
        $rawId = (string) ($arguments[3] ?? '');
        if ($rawId !== '' && (!ctype_digit($rawId) || (int) $rawId < 1)) {
            $this->json(400, ['ok' => false, 'error' => 'Article invalide.']);
        }
        $id = $rawId === '' ? 0 : (int) $rawId;
        $article = $id > 0 ? $this->articles->find($id) : null;
        if ($id > 0 && $article === null) {
            $this->json(404, ['ok' => false, 'error' => 'Article introuvable.']);
        }
        $category = $_POST['category_id'] ?? '';
        if (!is_scalar($category) || ($category !== '' && (!ctype_digit((string)$category) || (int)$category < 1))) {
            $this->json(400, ['ok' => false, 'error' => 'Catégorie invalide.']);
        }
        $categoryId = $category === '' ? null : (int) $category;
        if ($article !== null) {
            if (!$this->allowed($user, 'edit', $article, $categoryId)) {
                $this->json(403, ['ok' => false, 'error' => 'Accès refusé.']);
            }
        } elseif (!$user->isAdmin() && (!$user->articlePermissions()['create']
                || !$this->categoryAllowed($user, $categoryId))) {
            $this->json(403, ['ok' => false, 'error' => 'Accès refusé.']);
        }
        $base = $_POST['base_updated_at'] ?? '';
        if (!is_string($base)) {
            $this->json(400, ['ok' => false, 'error' => 'Version invalide.']);
        }
        if ($article !== null && !hash_equals((string)$article['updated_at'], $base)) {
            $this->json(409, ['ok' => false, 'error' => 'Cet article a été modifié ailleurs.']);
        }
        $fields = [];
        foreach (['title', 'slug', 'category_id', 'content', 'summary', 'author_name', 'featured_image'] as $key) {
            $value = $_POST[$key] ?? '';
            if (!is_string($value)) {
                $this->json(400, ['ok' => false, 'error' => 'Données invalides.']);
            }
            $fields[$key] = $value;
        }
        $fields['toc_enabled'] = isset($_POST['toc_enabled']) ? '1' : '0';
        $related = $_POST['related'] ?? [];
        $positions = $_POST['related_positions'] ?? [];
        if (!is_array($related) || !is_array($positions) || count($related) > 2000 || count($positions) > 2000) {
            $this->json(400, ['ok' => false, 'error' => 'Liste de liens invalide.']);
        }
        $fields['related'] = [];
        foreach ($related as $value) {
            if (!is_scalar($value) || !ctype_digit((string)$value)) {
                $this->json(400, ['ok' => false, 'error' => 'Lien invalide.']);
            }
            $fields['related'][] = (string)$value;
        }
        $fields['related_positions'] = [];
        foreach ($positions as $key => $value) {
            if (!ctype_digit((string)$key) || !is_scalar($value) || !ctype_digit((string)$value)) {
                $this->json(400, ['ok' => false, 'error' => 'Ordre invalide.']);
            }
            $fields['related_positions'][(string)$key] = (string)$value;
        }
        try {
            $this->articles->autosaves()->save($user->pseudo(), $id, $fields, $base);
            $this->json(200, ['ok' => true, 'saved_at' => gmdate('H:i')]);
        } catch (\Throwable $error) {
            error_log((string)$error);
            $this->json(500, ['ok' => false, 'error' => 'Sauvegarde automatique impossible.']);
        }
    }

    private function uploadImage(UserAccount $user): never
    {
        if (!$user->isAdmin() && !$user->isRedactor()) {
            $this->json(403, ['ok' => false, 'error' => $this->translator->text('security_access_denied')]);
        }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !\PortixSecurity::csrfIsValid()) {
            $this->json(403, ['ok' => false, 'error' => $this->translator->text('security_invalid_csrf')]);
        }
        try {
            $name = \PortixImageStorage::storeUploadedFile(
                $_FILES['image'] ?? [],
                $this->root . '/var/uploads/images'
            );
            $this->json(201, ['ok' => true, 'url' => 'var/uploads/images/' . $name]);
        } catch (Throwable $error) {
            error_log((string) $error);
            $this->json(422, ['ok' => false, 'error' => $this->translator->text('editor_upload_failed')]);
        }
    }

    /** @param array<string, mixed> $payload */
    private function json(int $status, array $payload): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');
        echo json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
        );
        exit;
    }

    private function deny(\Portix\View\Template $template): void
    {
        http_response_code(403);
        $template->setSlot('main', './var/admin/templates/access-denied.tpl');
    }
    private function eligibleRelatedArticles(UserAccount $user, ?int $id): array
    {
        $eligible = [];
        foreach ($this->articles->relatedArticleCandidates() as $candidate) {
            $candidateId = (int) $candidate['id'];
            $categoryId = $candidate['category_id'] === null ? null : (int) $candidate['category_id'];
            if ($candidateId !== $id && ($candidate['status'] === 'published'
                || $this->allowed($user, 'edit', $candidate, $categoryId))) {
                $eligible[$candidateId] = $candidate;
            }
        }
        return $eligible;
    }

    private function categoryAllowed(UserAccount $user, ?int $id): bool
    {
        return $user->isAdmin() || \Portix\Domain\ArticlePermissions::category($user->articlePermissions(), $id,
            $this->permissionCategories ??= $this->articles->flattenedCategories());
    }
    private function allowed(UserAccount $user, string $action, ?array $article, ?int $target): bool
    {
        return $user->isAdmin() || \Portix\Domain\ArticlePermissions::allows($user, $action, $article, $target,
            $this->permissionCategories ??= $this->articles->flattenedCategories());
    }
    private function numericId(mixed $value): int
    {
        if (!is_scalar($value) || ctype_digit((string) $value) !== true || (int) $value < 1) {
            throw new RuntimeException('articles_invalid_id');
        }
        return (int) $value;
    }

    private function optionalNumericId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        return $this->numericId($value);
    }

    /** @return array<string, list<string>> */
    private function postedMixedOrderGroups(mixed $value): array
    {
        if (!is_array($value)) {
            throw new RuntimeException('articles_invalid_order');
        }
        $groups = [];
        foreach ($value as $group => $tokens) {
            $group = (string) $group;
            if (($group !== 'root' && !ctype_digit($group)) || !is_array($tokens)) {
                throw new RuntimeException('articles_invalid_order');
            }
            $groups[$group] = [];
            foreach ($tokens as $token) {
                if (!is_scalar($token)
                    || !preg_match('/^(article|category):[1-9][0-9]*$/D', (string) $token)) {
                    throw new RuntimeException('articles_invalid_order');
                }
                $groups[$group][] = (string) $token;
            }
        }
        return $groups;
    }

}
