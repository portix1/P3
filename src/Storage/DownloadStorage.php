<?php
declare(strict_types=1);

namespace Portix\Storage;

use finfo;
use RuntimeException;

final class DownloadStorage
{
    private const MAX_BYTES = 20 * 1024 * 1024;
    private const ALLOWED = [
        'pdf' => ['application/pdf'],
        'zip' => ['application/zip', 'application/x-zip-compressed'],
        'png' => ['image/png'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'gif' => ['image/gif'],
        'txt' => ['text/plain'],
        'csv' => ['text/plain', 'text/csv', 'application/csv'],
        'tar' => ['application/x-tar'],
        'gz' => ['application/gzip', 'application/x-gzip'],
    ];

    public function __construct(private readonly string $directory)
    {
    }

    /** @param array<string, mixed> $upload @return array<string, mixed> */
    public function storeUpload(array $upload): array
    {
        if ((int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
            || !isset($upload['tmp_name'])
            || !is_uploaded_file((string) $upload['tmp_name'])) {
            throw new RuntimeException('downloads_upload_failed');
        }
        return $this->store((string) $upload['tmp_name'], (string) ($upload['name'] ?? ''), true);
    }

    /** @return array<string, mixed> */
    public function storeLocalFile(string $file, string $originalName): array
    {
        if (PHP_SAPI !== 'cli') {
            throw new RuntimeException('downloads_upload_failed');
        }
        return $this->store($file, $originalName, false);
    }

    public function path(string $storedName): string
    {
        if ($storedName === '' || basename($storedName) !== $storedName) {
            throw new RuntimeException('downloads_not_found');
        }
        return rtrim($this->directory, '/\\') . DIRECTORY_SEPARATOR . $storedName;
    }

    public function delete(string $storedName): void
    {
        $file = $this->path($storedName);
        if (is_file($file) && !unlink($file)) {
            throw new RuntimeException('downloads_delete_failed');
        }
    }

    /** @return array<string, mixed> */
    private function store(string $source, string $originalName, bool $uploaded): array
    {
        $originalName = \PortixSecurity::safeFilename($originalName);
        $size = is_file($source) ? filesize($source) : false;
        if (!is_int($size) || $size < 1 || $size > self::MAX_BYTES) {
            throw new RuntimeException('downloads_file_size');
        }
        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        if (!isset(self::ALLOWED[$extension])) {
            throw new RuntimeException('downloads_file_type');
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($source);
        if (!is_string($mime) || !in_array($mime, self::ALLOWED[$extension], true)) {
            throw new RuntimeException('downloads_file_type');
        }
        if (!$this->signatureMatches($source, $extension)) {
            throw new RuntimeException('downloads_file_type');
        }
        if (!is_dir($this->directory)
            && !mkdir($this->directory, 0770, true)
            && !is_dir($this->directory)) {
            throw new RuntimeException('downloads_private_directory_failed');
        }
        $storedName = bin2hex(random_bytes(24)) . '.' . $extension;
        $destination = $this->path($storedName);
        $moved = $uploaded ? move_uploaded_file($source, $destination) : copy($source, $destination);
        if (!$moved) {
            throw new RuntimeException('downloads_storage_failed');
        }
        @chmod($destination, 0640);
        return [
            'original_name' => $originalName,
            'stored_name' => $storedName,
            'mime_type' => $mime,
            'size_bytes' => $size,
            'sha256' => (string) hash_file('sha256', $destination),
        ];
    }

    private function signatureMatches(string $file, string $extension): bool
    {
        $handle = fopen($file, 'rb');
        if ($handle === false) {
            return false;
        }
        $head = (string) fread($handle, 512);
        fclose($handle);
        return match ($extension) {
            'pdf' => str_starts_with($head, '%PDF-'),
            'zip' => str_starts_with($head, "PK\x03\x04")
                || str_starts_with($head, "PK\x05\x06")
                || str_starts_with($head, "PK\x07\x08"),
            'png' => str_starts_with($head, "\x89PNG\r\n\x1a\n"),
            'jpg', 'jpeg' => str_starts_with($head, "\xff\xd8\xff"),
            'gif' => str_starts_with($head, 'GIF87a') || str_starts_with($head, 'GIF89a'),
            'gz' => str_starts_with($head, "\x1f\x8b"),
            'tar' => substr($head, 257, 5) === 'ustar',
            'txt', 'csv' => !str_contains($head, "\0"),
            default => false,
        };
    }
}
