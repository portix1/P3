<?php
declare(strict_types=1);

use Portix\Storage\DownloadStorage;
use Portix\Support\Slugger;

PortixSecurity::requireRole($user, 'admin');
require_once __DIR__ . '/src/Library.php';
$library = new \PortixModule\Download2\Library($database->connection());
$storage = new DownloadStorage(dirname(__DIR__, 3) . '/var/private/download2');
$view = (string) ($argument[2] ?? 'list');
$message = '';

try {
    if (isset($_POST['dl2_action'])) {
        PortixSecurity::requirePostWithCsrf();
        $action = (string) $_POST['dl2_action'];
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($action === 'create_category') {
            $name = trim((string) ($_POST['name'] ?? ''));
            $slug = Slugger::fromText($name, 'categorie');
            $library->createCategory($name, $slug, '');
            $view = 'categories';
            $message = $translator->text('dl2_saved');
        } elseif ($action === 'update_category' && $id !== false) {
            $library->updateCategory((int) $id, (string) ($_POST['name'] ?? ''), dl2Description((string) ($_POST['description'] ?? '')));
            $view = 'categories';
            $message = $translator->text('dl2_saved');
        } elseif ($action === 'delete_category' && $id !== false) {
            $library->deleteCategory((int) $id);
            $view = 'categories';
            $message = $translator->text('dl2_deleted');
        } elseif ($action === 'create') {
            $stored = $storage->storeUpload((array) ($_FILES['file'] ?? []));
            try {
                $library->create([
                    ...$stored,
                    'category_id' => dl2Category($library, $_POST['category_id'] ?? null),
                    'title' => dl2Title((string) ($_POST['title'] ?? ''), $stored['original_name']),
                    'description' => dl2Description((string) ($_POST['description'] ?? '')),
                    'status' => dl2Status((string) ($_POST['status'] ?? 'draft')),
                    'show_in_library' => isset($_POST['show_in_library']) ? 1 : 0,
                    'show_in_library' => isset($_POST['show_in_library']) ? 1 : 0,
                ]);
            } catch (Throwable $e) {
                $storage->delete((string) $stored['stored_name']);
                throw $e;
            }
            $message = $translator->text('dl2_saved');
            $view = 'list';
        } elseif ($action === 'update' && $id !== false) {
            $old = $library->find((int) $id);
            if ($old === null) { throw new RuntimeException('dl2_missing'); }
            $stored = null;
            if ((int) ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $stored = $storage->storeUpload((array) $_FILES['file']);
            }
            try {
                $library->update((int) $id, [
                    ...($stored ?? []),
                    'category_id' => dl2Category($library, $_POST['category_id'] ?? null),
                    'title' => dl2Title((string) ($_POST['title'] ?? ''), (string) $old['original_name']),
                    'description' => dl2Description((string) ($_POST['description'] ?? '')),
                    'status' => dl2Status((string) ($_POST['status'] ?? 'draft')),
                ]);
            } catch (Throwable $e) {
                if ($stored !== null) { $storage->delete((string) $stored['stored_name']); }
                throw $e;
            }
            if ($stored !== null) {
                try { $storage->delete((string) $old['stored_name']); }
                catch (Throwable $e) { error_log('Download2 orphaned old file: ' . $e->getMessage()); }
            }
            $view = 'list';
            $message = $translator->text('dl2_saved');
        } elseif ($action === 'delete' && $id !== false) {
            $old = $library->find((int) $id);
            if ($old === null) { throw new RuntimeException('dl2_missing'); }
            $library->delete((int) $id);
            try { $storage->delete((string) $old['stored_name']); }
            catch (Throwable $e) { error_log('Download2 orphaned deleted file: ' . $e->getMessage()); }
            $view = 'list';
            $message = $translator->text('dl2_deleted');
        } else {
            throw new RuntimeException('dl2_invalid_file');
        }
    }
} catch (Throwable $e) {
    $message = $translator->exception($e);
}

$template->set('dl2_message', $message);
$template->set('dl2_manage', $translator->text('dl2_manage'));
$template->set('dl2_manage_help', $translator->text('dl2_manage_help'));
$template->set('dl2_form_title', $translator->text('dl2_add'));
$template->set('dl2_form_action', 'create');
$template->set('dl2_id', '');
$template->set('dl2_title_value', '');
$template->set('dl2_description_value', '');
$template->set('dl2_draft_selected', 'selected');
$template->set('dl2_published_selected', '');
$template->set('dl2_file_required', 'required');
$template->set('dl2_show_in_library_checked', 'checked');
$template->set('dl2_category_none_selected', 'selected');
$template->set('dl2_file_current', '');
$template->set('dl2_is_edit', '0');

if ($view === 'new' || $view === 'edit') {
    $selectedCategory = null;
    if ($view === 'edit') {
        $id = filter_var($argument[3] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        $item = $id === false ? null : $library->find((int) $id);
        if ($item === null) { http_response_code(404); throw new RuntimeException('dl2_missing'); }
        $template->set('dl2_form_title', $translator->text('dl2_edit'));
        $template->set('dl2_form_action', 'update');
        $template->set('dl2_id', (string) $item['id']);
        $template->set('dl2_title_value', (string) $item['title']);
        $template->set('dl2_description_value', (string) $item['description']);
        $template->set('dl2_draft_selected', $item['status'] === 'draft' ? 'selected' : '');
        $template->set('dl2_published_selected', $item['status'] === 'published' ? 'selected' : '');
        $template->set('dl2_file_required', '');
        $template->set('dl2_file_current', (string) $item['original_name']);
        $template->set('dl2_is_edit', '1');
        $template->set('dl2_show_in_library_checked', (int) $item['show_in_library'] === 1 ? 'checked' : '');
        $selectedCategory = $item['category_id'] === null ? null : (int) $item['category_id'];
    }
    $template->set('dl2_category_none_selected', $selectedCategory === null ? 'selected' : '');
    foreach ($library->categories() as $category) {
        $template->addRow('dl2_category_option', [
            'id'=>(string)$category['id'], 'name'=>(string)$category['name'],
            'selected'=>(int)$category['id']===$selectedCategory ? 'selected' : '',
        ]);
    }
    $template->setSlot('main', './var/modules/download2/templates/form.tpl');
    return;
}

if ($view === 'categories') {
    $template->setSlot('main', './var/modules/download2/templates/categories.tpl');
    foreach ($library->categories() as $category) {
        $template->addRow('dl2_category_row', [
            'id'=>(string)$category['id'], 'name'=>(string)$category['name'],
            'description'=>(string)$category['description'],
            'usage'=>(string)$library->categoryUsage((int)$category['id']),
            'can_delete'=>$library->categoryUsage((int)$category['id']) === 0 ? '1' : '0',
        ]);
    }
    return;
}

$template->setSlot('main', './var/modules/download2/templates/admin.tpl');
foreach ($library->allForAdmin() as $item) {
    $fileMissing = false;
    try {
        $onDisk = $storage->path((string)$item['stored_name']);
        $fileMissing = !is_file($onDisk) || is_link($onDisk);
    } catch (Throwable) {
        $fileMissing = true;
    }
    $template->addRow('dl2_admin_file', [
        'storage_warning' => $fileMissing ? $translator->text('dl2_missing_storage') : '',
        'id'=>(string)$item['id'], 'title'=>(string)$item['title'],
        'category'=>(string)($item['category_name'] ?? $translator->text('dl2_without_category')),
        'status'=>$translator->text('dl2_' . $item['status']),
        'counter'=>(string)$item['download_count'],
        'library_visibility'=>(int)$item['show_in_library'] === 1 ? $translator->text('dl2_library_yes') : $translator->text('dl2_library_no'),
        'name'=>(string)$item['original_name'],
    ]);
}

function dl2Category(\PortixModule\Download2\Library $library, mixed $id): ?int
{
    if ($id === '' || $id === null) { return null; }
    $id = filter_var($id, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
    if ($id === false) { throw new RuntimeException('dl2_invalid_category'); }
    foreach ($library->categories() as $category) {
        if ((int)$category['id'] === (int)$id) { return (int)$id; }
    }
    throw new RuntimeException('dl2_invalid_category');
}
function dl2Title(string $title, string $fallback): string
{
    $title = trim(strip_tags($title));
    $title = $title === '' ? $fallback : $title;
    if ($title === '' || mb_strlen($title) > 200) { throw new RuntimeException('dl2_invalid_file'); }
    return $title;
}
function dl2Description(string $description): string
{
    $description = trim(strip_tags($description));
    if (mb_strlen($description) > 5000) { throw new RuntimeException('dl2_invalid_file'); }
    return $description;
}
function dl2Status(string $status): string
{
    if (!in_array($status, ['draft','published'], true)) { throw new RuntimeException('dl2_invalid_file'); }
    return $status;
}
