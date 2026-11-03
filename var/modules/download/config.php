<?php
declare(strict_types=1);

use Portix\Observability\EventLogger;
use Portix\Storage\DownloadStorage;
use Portix\Support\Slugger;

PortixSecurity::requireRole($user, 'admin');
$storage = new DownloadStorage(dirname(__DIR__, 3) . '/var/private/downloads');
$logger = new EventLogger($database->connection());
$view = (string) ($argument[2] ?? 'list');
$message = '';

if (isset($_POST['download_action'])) {
    try {
        PortixSecurity::requirePostWithCsrf();
        $action = (string) $_POST['download_action'];
        if ($action === 'create_file') {
            $categoryId = downloadCategoryId($repositories, $_POST['category_id'] ?? null);
            $stored = $storage->storeUpload((array) ($_FILES['file'] ?? []));
            try {
                $id = $repositories->downloads()->create([
                    ...$stored,
                    'category_id' => $categoryId,
                    'title' => downloadTitle((string) ($_POST['title'] ?? ''), (string) $stored['original_name']),
                    'description' => downloadDescription((string) ($_POST['description'] ?? '')),
                    'status' => downloadStatus((string) ($_POST['status'] ?? 'draft')),
                ]);
            } catch (Throwable $error) {
                $storage->delete((string) $stored['stored_name']);
                throw $error;
            }
            $logger->audit($user->pseudo(), 'download.create', 'download:' . $id, [], $requestId);
            $message = $translator->text('downloads_file_saved');
            $view = 'list';
        } elseif ($action === 'update_file') {
            $id = downloadId($_POST['id'] ?? null);
            $download = $repositories->downloads()->find($id);
            if ($download === null) {
                throw new RuntimeException($translator->text('downloads_not_found'));
            }
            $repositories->downloads()->update($id, [
                'category_id' => downloadCategoryId($repositories, $_POST['category_id'] ?? null),
                'title' => downloadTitle((string) ($_POST['title'] ?? ''), (string) $download['original_name']),
                'description' => downloadDescription((string) ($_POST['description'] ?? '')),
                'status' => downloadStatus((string) ($_POST['status'] ?? 'draft')),
            ]);
            if (isset($_FILES['file']) && (int) ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $stored = $storage->storeUpload((array) $_FILES['file']);
                try {
                    $repositories->downloads()->replaceFile($id, $stored);
                } catch (Throwable $error) {
                    $storage->delete((string) $stored['stored_name']);
                    throw $error;
                }
                try {
                    $storage->delete((string) $download['stored_name']);
                } catch (Throwable $error) {
                    $logger->application(
                        'warning', 'download.orphan_file', $error->getMessage(), ['download_id' => $id], $requestId
                    );
                }
            }
            $logger->audit($user->pseudo(), 'download.update', 'download:' . $id, [], $requestId);
            $message = $translator->text('downloads_file_updated');
            $view = 'list';
        } elseif ($action === 'delete_file') {
            $id = downloadId($_POST['id'] ?? null);
            $download = $repositories->downloads()->find($id);
            if ($download === null) {
                throw new RuntimeException($translator->text('downloads_not_found'));
            }
            $repositories->downloads()->delete($id);
            try {
                $storage->delete((string) $download['stored_name']);
            } catch (Throwable $error) {
                $logger->application(
                    'warning', 'download.orphan_file', $error->getMessage(), ['download_id' => $id], $requestId
                );
            }
            $logger->audit($user->pseudo(), 'download.delete', 'download:' . $id, [], $requestId);
            $message = $translator->text('downloads_file_deleted');
            $view = 'list';
        } elseif ($action === 'create_category') {
            $name = downloadCategoryName((string) ($_POST['name'] ?? ''));
            $base = Slugger::fromText((string) ($_POST['slug'] ?? '') ?: $name, 'categorie');
            $id = $repositories->downloads()->createCategory(
                $name,
                $repositories->downloads()->uniqueCategorySlug($base),
                downloadDescription((string) ($_POST['description'] ?? ''))
            );
            $logger->audit($user->pseudo(), 'download.category_create', 'download_category:' . $id, [], $requestId);
            $message = $translator->text('downloads_category_saved');
            $view = 'categories';
        } elseif ($action === 'update_category') {
            $id = downloadId($_POST['id'] ?? null);
            $category = $repositories->downloads()->findCategory($id);
            if ($category === null) {
                throw new RuntimeException($translator->text('downloads_category_not_found'));
            }
            $name = downloadCategoryName((string) ($_POST['name'] ?? ''));
            $base = Slugger::fromText((string) ($_POST['slug'] ?? '') ?: $name, 'categorie');
            $repositories->downloads()->updateCategory(
                $id,
                $name,
                $repositories->downloads()->uniqueCategorySlug($base, $id),
                downloadDescription((string) ($_POST['description'] ?? ''))
            );
            $logger->audit($user->pseudo(), 'download.category_update', 'download_category:' . $id, [], $requestId);
            $message = $translator->text('downloads_category_updated');
            $view = 'categories';
        } elseif ($action === 'delete_category') {
            $id = downloadId($_POST['id'] ?? null);
            $category = $repositories->downloads()->findCategory($id);
            if ($category === null) {
                throw new RuntimeException($translator->text('downloads_category_not_found'));
            }
            foreach ($repositories->downloads()->categories() as $row) {
                if ((int) $row['id'] === $id && (int) $row['file_count'] > 0) {
                    throw new RuntimeException($translator->text('downloads_category_not_empty'));
                }
            }
            $repositories->downloads()->deleteCategory($id);
            $logger->audit($user->pseudo(), 'download.category_delete', 'download_category:' . $id, [], $requestId);
            $message = $translator->text('downloads_category_deleted');
            $view = 'categories';
        }
    } catch (Throwable $error) {
        $message = $translator->exception($error);
    }
}

$template->set('downloads_message', $message);
$template->set('downloads_list_current', $view === 'list' ? 'is-current' : '');
$template->set('downloads_new_current', $view === 'new' ? 'is-current' : '');
$template->set('downloads_categories_current', $view === 'categories' ? 'is-current' : '');

if ($view === 'new') {
    $template->setSlot('main', 'var/modules/download/templates/admin-form.tpl');
    $template->set('downloads_form_title', $translator->text('downloads_add_file'));
    $template->set('downloads_form_action', 'create_file');
    $template->set('downloads_file_id', '');
    $template->set('downloads_title_value', '');
    $template->set('downloads_description_value', '');
    $template->set('downloads_status_draft_selected', 'selected');
    $template->set('downloads_status_published_selected', '');
    $template->set('downloads_file_required', 'required');
    $template->set('downloads_current_file', '');
    $template->set('downloads_is_edit', '0');
    downloadAddCategories($template, $repositories, null);
    return;
}
if ($view === 'edit' && isset($argument[3]) && ctype_digit((string) $argument[3])) {
    $download = $repositories->downloads()->find((int) $argument[3]);
    if ($download === null) {
        throw new RuntimeException($translator->text('downloads_not_found'));
    }
    $template->setSlot('main', 'var/modules/download/templates/admin-form.tpl');
    $template->set('downloads_form_title', $translator->text('downloads_edit_file'));
    $template->set('downloads_form_action', 'update_file');
    $template->set('downloads_file_id', (string) $download['id']);
    $template->set('downloads_title_value', (string) $download['title']);
    $template->set('downloads_description_value', (string) $download['description']);
    $template->set('downloads_status_draft_selected', $download['status'] === 'draft' ? 'selected' : '');
    $template->set('downloads_status_published_selected', $download['status'] === 'published' ? 'selected' : '');
    $template->set('downloads_file_required', '');
    $template->set('downloads_current_file', (string) $download['original_name']);
    $template->set('downloads_is_edit', '1');
    downloadAddCategories($template, $repositories, (int) $download['category_id']);
    return;
}
if ($view === 'categories') {
    $template->setSlot('main', 'var/modules/download/templates/admin-categories.tpl');
    foreach ($repositories->downloads()->categories() as $row) {
        $template->addRow('downloadcategoryrow', [
            'id' => (string) $row['id'],
            'name' => (string) $row['name'],
            'slug' => (string) $row['slug'],
            'description' => (string) $row['description'],
            'count' => (string) $row['file_count'],
        ]);
    }
    return;
}
if ($view === 'category' && isset($argument[3]) && ctype_digit((string) $argument[3])) {
    $category = $repositories->downloads()->findCategory((int) $argument[3]);
    if ($category === null) {
        throw new RuntimeException($translator->text('downloads_category_not_found'));
    }
    $template->setSlot('main', 'var/modules/download/templates/admin-category-form.tpl');
    $template->set('downloads_category_form_action', 'update_category');
    $template->set('downloads_category_id', (string) $category['id']);
    $template->set('downloads_category_name_value', (string) $category['name']);
    $template->set('downloads_category_slug_value', (string) $category['slug']);
    $template->set('downloads_category_description_value', (string) $category['description']);
    $template->set('downloads_category_form_title', $translator->text('downloads_edit_category'));
    return;
}
if ($view === 'newcategory') {
    $template->setSlot('main', 'var/modules/download/templates/admin-category-form.tpl');
    $template->set('downloads_category_form_action', 'create_category');
    $template->set('downloads_category_id', '');
    $template->set('downloads_category_name_value', '');
    $template->set('downloads_category_slug_value', '');
    $template->set('downloads_category_description_value', '');
    $template->set('downloads_category_form_title', $translator->text('downloads_new_category'));
    return;
}

$template->setSlot('main', 'var/modules/download/templates/admin-list.tpl');
foreach ($repositories->downloads()->all() as $row) {
    $template->addRow('downloadrow', [
        'id' => (string) $row['id'],
        'title' => (string) $row['title'],
        'category' => (string) ($row['category_name'] ?? ''),
        'status' => (string) $row['status'],
        'status_label' => $translator->text('downloads_status_' . (string) $row['status']),
        'counter' => (string) $row['download_count'],
        'size' => number_format(((int) $row['size_bytes']) / 1048576, 2, ',', ' ') . ' Mio',
    ]);
}

function downloadId(mixed $value): int
{
    $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false) {
        throw new RuntimeException('downloads_invalid_id');
    }
    return (int) $id;
}

function downloadCategoryId($repositories, mixed $value): int
{
    $id = downloadId($value);
    if ($repositories->downloads()->findCategory($id) === null) {
        throw new RuntimeException('downloads_category_not_found');
    }
    return $id;
}

function downloadTitle(string $title, string $fallback): string
{
    $title = trim($title) ?: $fallback;
    if ($title === '' || mb_strlen($title) > 200) {
        throw new RuntimeException('downloads_invalid_title');
    }
    return $title;
}

function downloadCategoryName(string $name): string
{
    $name = trim($name);
    if ($name === '' || mb_strlen($name) > 120) {
        throw new RuntimeException('downloads_invalid_category');
    }
    return $name;
}

function downloadDescription(string $description): string
{
    $description = trim(strip_tags($description));
    if (mb_strlen($description) > 5000) {
        throw new RuntimeException('downloads_invalid_description');
    }
    return $description;
}

function downloadStatus(string $status): string
{
    if (!in_array($status, ['draft', 'published'], true)) {
        throw new RuntimeException('downloads_invalid_status');
    }
    return $status;
}

function downloadAddCategories($template, $repositories, ?int $selected): void
{
    foreach ($repositories->downloads()->categories() as $category) {
        $template->addRow('downloadformcategory', [
            'id' => (string) $category['id'],
            'name' => (string) $category['name'],
            'selected' => (int) $category['id'] === $selected ? 'selected' : '',
        ]);
    }
}
