<?php
declare(strict_types=1);

require_once __DIR__ . '/src/Library.php';
$library = new \PortixModule\Download2\Library($database->connection());
$action = (string) ($argument[1] ?? '');
if ($action === 'file') {
    require_once __DIR__ . '/src/Transfer.php';
    $id = (string) ($argument[2] ?? '');
    \PortixModule\Download2\Transfer::send(
        $library, dirname(__DIR__, 3), ctype_digit($id) ? (int) $id : 0, $request->method()
    );
}
if ($action === 'catalog') {
    if (!in_array($request->method(), ['GET', 'HEAD'], true)) {
        header('Allow: GET, HEAD'); http_response_code(405); exit;
    }
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    $files = array_map(
        static fn (array $file): array => [
            'id' => (int) $file['id'], 'title' => (string) $file['title'],
            'category' => (string) ($file['category_name'] ?? ''),
        ],
        $library->published()
    );
    if ($request->method() === 'GET') {
        echo json_encode(['files' => $files], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
    exit;
}
if ($action !== '') { http_response_code(404); $pageMetadata->noIndex(); return; }
$template->setSlot('main', './var/modules/download2/templates/public.tpl');
$template->set('dl2_has_files', '0');
$storage = new \Portix\Storage\DownloadStorage(dirname(__DIR__, 3) . '/var/private/download2');
$count = 0;
foreach ($library->publicLibrary() as $file) {
    try {
        $path = $storage->path((string) $library->find((int) $file['id'], true)['stored_name']);
        if (!is_file($path) || is_link($path)) { continue; }
    } catch (\Throwable) { continue; }
    $template->addRow('dl2_public_file', [
        'title' => (string) $file['title'],
        'description' => (string) $file['description'],
        'category' => (string) ($file['category_name'] ?? ''),
        'size' => number_format((int)$file['size_bytes'] / 1024, 0, ',', ' ') . ' Kio',
        'url' => 'download-file-' . (int) $file['id'] . '.html',
    ]);
    $count++;
}
$template->set('dl2_has_files', $count ? '1' : '0');
$pageMetadata->define($translator->text('dl2_title'), $translator->text('dl2_intro'), 'download.html');
