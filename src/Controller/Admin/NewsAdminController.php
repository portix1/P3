<?php
declare(strict_types=1);

namespace Portix\Controller\Admin;

use Portix\Domain\UserAccount;
use Portix\Domain\NewsPermissions;
use Portix\Repository\NewsRepository;
use Portix\Repository\ContentSafetyRepository;
use Portix\Support\Slugger;
use Portix\Support\Translator;
use RuntimeException;
use Throwable;

final class NewsAdminController
{
    /** @param list<string> $reservedSlugs */
    public function __construct(
        private readonly NewsRepository $news,
        private readonly string $root,
        private readonly array $reservedSlugs,
        private readonly Translator $translator,
        private readonly ContentSafetyRepository $safety
    ) {
    }

    /** @param list<string> $arguments */
    public function handle(\Portix\View\Template $template, array $arguments, UserAccount $user): void
    {
        require_once $this->root . '/includes/class/html_sanitizer.class.php';
        require_once $this->root . '/includes/class/image_storage.class.php';

        if (!$user->isAdmin() && !$user->isRedactor()) { $this->deny($template); return; }
        $action = (string) ($arguments[2] ?? 'list');
        if ($action === 'delete' && !NewsPermissions::allows($user, 'delete')) { $this->deny($template); return; }
        if (in_array($action, ['new', 'edit', 'preview'], true)) {
            $id = $action === 'new' || !isset($arguments[3]) ? null : $this->numericId($arguments[3]);
            $target = $id === null ? null : $this->news->find($id);
            if (($id !== null && $target === null) || ($action === 'edit' && $id === null) || !NewsPermissions::allows($user, 'edit', $target)) {
                $this->deny($template); return;
            }
        }
        if ($action === 'upload' && ($arguments[3] ?? '') === 'image') {
            $this->uploadImage($user);
        }

        if ($action === 'delete') {
            $this->delete($arguments, $user);
        }
        if ($action === 'preview') {
            $this->preview($arguments, $user);
        }

        if ($action === 'new' || $action === 'edit') {
            $this->edit($template, $arguments, $user, $action === 'new');
            return;
        }

        $this->listing($template, $user);
    }

    private function listing(\Portix\View\Template $template, UserAccount $user): void
    {
        $template->setSlot('main', './var/admin/templates/news-list.tpl');
        $rows = array_values(array_filter($this->news->allForAdministration(), static fn(array $row): bool => NewsPermissions::allows($user, 'edit', $row)));
        $template->set('news_total', (string) count($rows));
        $template->set('news_published', (string) count(array_filter($rows, static fn(array $row): bool => $row['status'] === 'published')));
        $template->set('news_can_create', NewsPermissions::allows($user, 'edit') ? '1' : '0');
        foreach ($rows as $row) {
            $published = (string) $row['status'] === 'published';
            $template->addRow('newsrow', [
                'id' => (string) $row['id'],
                'title' => (string) $row['title'],
                'slug' => (string) $row['slug'],
                'created_by' => (string) $row['created_by'],
                'status' => $published
                    ? $this->translator->text('news_admin_published')
                    : $this->translator->text('news_admin_draft'),
                'status_class' => $published ? 'state-enabled' : 'state-draft',
                'date' => (string) ($row['published_at'] ?: $row['updated_at']),
                'public_url' => $published ? (string) $row['slug'] . '.html' : '',
                'is_pinned' => !empty($row['is_pinned']) ? '1' : '0',
                'pin_label' => !empty($row['is_pinned'])
                    ? $this->translator->text('news_admin_pinned')
                    : '',
                'can_delete' => $user->isAdmin() ? '1' : '0',
            ]);
        }
    }

    /** @param list<string> $arguments */
    private function edit(\Portix\View\Template $template, array $arguments, UserAccount $user, bool $isNew): void
    {
        $id = $isNew ? null : $this->numericId($arguments[3] ?? null);
        $existing = $id === null ? null : $this->news->find($id);
        if (!$isNew && $existing === null) {
            throw new RuntimeException($this->translator->text('news_admin_not_found'));
        }

        $draft = $existing ?? [
            'title' => '', 'slug' => '', 'intro' => '', 'body' => '',
            'status' => 'draft', 'published_at' => null, 'is_pinned' => 0,
        ];
        $message = '';

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            try {
                \PortixSecurity::requirePostWithCsrf();
                $title = trim(strip_tags((string) ($_POST['title'] ?? '')));
                $titleLength = function_exists('mb_strlen')
                    ? mb_strlen($title, 'UTF-8')
                    : strlen($title);
                if ($title === '' || $titleLength > 250) {
                    throw new RuntimeException($this->translator->text('news_admin_invalid_title'));
                }
                $status = ($_POST['status'] ?? '') === 'published' ? 'published' : 'draft';
                if (($status === 'published' || ($existing['status'] ?? '') === 'published') && !NewsPermissions::allows($user, 'publish', $existing)) {
                    throw new RuntimeException($this->translator->text('news_admin_access_denied'));
                }
                $slugSource = trim((string) ($_POST['slug'] ?? ''));
                $slugBase = Slugger::fromText($slugSource !== '' ? $slugSource : $title);
                $slug = $this->news->uniqueSlug($slugBase, $this->reservedSlugs, $id);
                $intro = \PortixHtmlSanitizer::clean($_POST['intro'] ?? '');
                $body = \PortixHtmlSanitizer::clean($_POST['content'] ?? '');
                $intro = \PortixImageStorage::optimiseReferencedFilenames($intro, $this->root . '/var/uploads/images');
                $body = \PortixImageStorage::optimiseReferencedFilenames($body, $this->root . '/var/uploads/images');
                $pinned = NewsPermissions::allows($user, 'pin') ? isset($_POST['is_pinned']) : !empty($existing['is_pinned']);
                $now = gmdate('Y-m-d H:i:s');
                $publishedAt = $status === 'published'
                    ? ((string) ($existing['published_at'] ?? '') ?: $now)
                    : null;

                if ($id === null) {
                    $savedId = $this->news->create(
                        $title, $slug, $user->pseudo(), $intro, $body, $status, $publishedAt, $now
                    );
                    if (NewsPermissions::allows($user, 'pin')) { $this->news->setPinned($savedId, $pinned); }
                } else {
                    $this->safety->captureRevision('news', $id, $existing, $user->pseudo());
                    $this->news->update($id, $title, $slug, $intro, $body, $status, $publishedAt, $now);
                    if (NewsPermissions::allows($user, 'pin')) { $this->news->setPinned($id, $pinned); }
                }
                header('Location: Admin-news.html');
                exit;
            } catch (RuntimeException $error) {
                $message = $this->translator->exception($error);
                $draft = [
                    'title' => (string) ($_POST['title'] ?? ''),
                    'slug' => (string) ($_POST['slug'] ?? ''),
                    'intro' => \PortixHtmlSanitizer::clean($_POST['intro'] ?? ''),
                    'body' => \PortixHtmlSanitizer::clean($_POST['content'] ?? ''),
                    'status' => ($_POST['status'] ?? '') === 'published' ? 'published' : 'draft',
                    'published_at' => $existing['published_at'] ?? null,
                    'is_pinned' => isset($_POST['is_pinned']) ? 1 : 0,
                ];
            }
        }

        $template->setSlot('main', './var/admin/templates/news-form.tpl');
        $template->set(
            'news_form_title',
            $this->translator->text($isNew ? 'news_admin_form_new' : 'news_admin_form_edit')
        );
        $template->set('news_form_action', $isNew
            ? 'Admin-news-new.html'
            : 'Admin-news-edit-' . $id . '.html');
        $template->set('news_message', $message);
        $template->set('news_title', (string) $draft['title']);
        $template->set('news_slug', (string) $draft['slug']);
        $template->set('news_intro', (string) $draft['intro']);
        $template->set('news_content', (string) $draft['body']);
        $template->set('news_preview_action', $id === null
            ? 'Admin-news-preview.html'
            : 'Admin-news-preview-' . $id . '.html');
        $template->set('news_can_publish', NewsPermissions::allows($user, 'publish', $existing) ? '1' : '0');
        $template->set('news_can_pin', NewsPermissions::allows($user, 'pin') ? '1' : '0');
        $template->set('news_draft_selected', $draft['status'] === 'draft' ? 'selected' : '');
        $template->set('news_published_selected', $draft['status'] === 'published' ? 'selected' : '');
        $template->set('news_pinned_checked', !empty($draft['is_pinned']) ? 'checked' : '');
    }

    /** @param list<string> $arguments */
    private function preview(array $arguments, UserAccount $user): never
    {
        \PortixSecurity::requirePostWithCsrf();
        $id = isset($arguments[3]) ? $this->numericId($arguments[3]) : null;
        $existing = $id === null ? null : $this->news->find($id);
        if ($id !== null && $existing === null) {
            throw new RuntimeException($this->translator->text('news_admin_not_found'));
        }

        if (array_key_exists('title', $_POST)) {
            $title = trim(strip_tags((string) ($_POST['title'] ?? '')));
            $slug = trim((string) ($_POST['slug'] ?? ''));
            $intro = \PortixHtmlSanitizer::clean($_POST['intro'] ?? '');
            $body = \PortixHtmlSanitizer::clean($_POST['content'] ?? '');
        } elseif ($existing !== null) {
            $title = (string) $existing['title'];
            $slug = (string) $existing['slug'];
            $intro = (string) $existing['intro'];
            $body = (string) $existing['body'];
        } else {
            $title = '';
            $slug = '';
            $intro = '';
            $body = '';
        }
        if ($title === '') {
            $title = $this->translator->text('content_preview_untitled');
        }
        $now = gmdate('Y-m-d H:i:s');
        $token = (new \Portix\Preview\SessionPreviewStore())->create('news', [
            'title' => $title,
            'slug' => $slug,
            'intro' => $intro,
            'body' => $body,
            'created_by' => (string) ($existing['created_by'] ?? $user->pseudo()),
            'published_at' => (string) ($existing['published_at'] ?? $now),
            'updated_at' => $now,
            'is_pinned' => NewsPermissions::allows($user, 'pin') && isset($_POST['is_pinned'])
                ? 1
                : (int) ($existing['is_pinned'] ?? 0),
        ]);
        header('Location: news-preview-' . $token . '.html');
        exit;
    }

    /** @param list<string> $arguments */
    private function delete(array $arguments, UserAccount $user): never
    {
        if (!$user->isAdmin()) {
            throw new RuntimeException($this->translator->text('news_admin_delete_forbidden'));
        }
        \PortixSecurity::requirePostWithCsrf();
        $id = $this->numericId($arguments[3] ?? null);
        $existing = $this->news->find($id);
        if ($existing === null) {
            throw new RuntimeException($this->translator->text('news_admin_not_found'));
        }
        $this->safety->moveToTrash('news', $id, (string) $existing['title'], $existing, $user->pseudo());
        $this->news->delete($id);
        header('Location: Admin-news.html');
        exit;
    }

    private function uploadImage(UserAccount $user): never
    {
        if (!$user->isAdmin() && !$user->isRedactor()) {
            $this->json(403, ['ok' => false, 'error' => $this->translator->text('news_admin_access_denied')]);
        }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !\PortixSecurity::csrfIsValid()) {
            $this->json(403, ['ok' => false, 'error' => $this->translator->text('news_admin_invalid_request')]);
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

    private function numericId(mixed $value): int
    {
        if (!is_scalar($value) || !ctype_digit((string) $value) || (int) $value < 1) {
            throw new RuntimeException($this->translator->text('news_admin_invalid_id'));
        }
        return (int) $value;
    }
}
