<?php
declare(strict_types=1);

namespace PortixModule\Download2;

use Portix\Storage\DownloadStorage;

final class Transfer
{
    public static function send(Library $library, string $root, int $id, string $method): never
    {
        \PortixSecurity::sendHeaders();
        if (!in_array($method, ['GET', 'HEAD'], true)) {
            header('Allow: GET, HEAD'); http_response_code(405); exit;
        }
        $item = $library->find($id, true);
        if ($item === null) { http_response_code(404); exit; }
        $store = new DownloadStorage($root . '/var/private/download2');
        try { $file = $store->path((string) $item['stored_name']); }
        catch (\Throwable) { http_response_code(404); exit; }
        if (!is_file($file) || is_link($file)) { http_response_code(404); exit; }
        $length = filesize($file);
        if (!is_int($length) || $length < 1) { http_response_code(404); exit; }
        $safeName = \PortixSecurity::safeFilename((string) $item['original_name']);
        header("Content-Disposition: attachment; filename*=UTF-8''" . rawurlencode($safeName));
        header('Content-Type: application/octet-stream');
        header('Content-Length: ' . $length);
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        if ($method === 'GET') {
            $library->increment($id);
            readfile($file);
        }
        exit;
    }
}
