<?php
declare(strict_types=1);

namespace PortixModule\Download;

use Portix\Repository\DownloadRepository;
use Portix\Storage\DownloadStorage;
use Portix\Support\Translator;

final class DownloadResponse
{
    public static function send(
        DownloadRepository $downloads,
        Translator $translator,
        string $root,
        int $id,
        string $method
    ): never {
        \PortixSecurity::sendHeaders();
        $download = $downloads->find($id, true);
        if ($download === null) {
            http_response_code(404);
            exit($translator->text('downloads_not_found'));
        }
        $storage = new DownloadStorage($root . '/var/private/downloads');
        try {
            $path = $storage->path((string) $download['stored_name']);
        } catch (\Throwable) {
            http_response_code(404);
            exit($translator->text('downloads_not_found'));
        }
        if (!is_file($path)) {
            http_response_code(404);
            exit($translator->text('downloads_not_found'));
        }

        $displayName = \PortixSecurity::safeFilename((string) $download['original_name']);
        $mimeType = (string) $download['mime_type'];
        if ($mimeType === '' || preg_match('!^[a-z0-9.+-]+/[a-z0-9.+-]+$!i', $mimeType) !== 1) {
            $mimeType = 'application/octet-stream';
        }
        header("Content-Disposition: attachment; filename*=UTF-8''" . rawurlencode($displayName));
        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . filesize($path));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        if ($method !== 'HEAD') {
            $downloads->incrementCounter((int) $download['id']);
            readfile($path);
        }
        exit;
    }
}
