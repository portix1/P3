<?php
declare(strict_types=1);

namespace Portix\Preview;

/**
 * Short-lived previews stored inside the authenticated PHP session.
 * Tokens are intentionally useless outside the session that created them.
 */
final class SessionPreviewStore
{
    private const SESSION_KEY = 'portix_content_previews';
    private const TTL = 1800;
    private const MAX_ENTRIES = 20;

    /** @param array<string, mixed> $payload */
    public function create(string $kind, array $payload): string
    {
        $this->purge();
        $token = bin2hex(random_bytes(16));
        $_SESSION[self::SESSION_KEY][$token] = [
            'kind' => $kind,
            'created_at' => time(),
            'payload' => $payload,
        ];
        $this->trim();
        return $token;
    }

    /** @return array<string, mixed>|null */
    public function get(string $kind, string $token): ?array
    {
        if (preg_match('/^[a-f0-9]{32}$/', $token) !== 1) {
            return null;
        }
        $this->purge();
        $entry = $_SESSION[self::SESSION_KEY][$token] ?? null;
        if (!is_array($entry) || ($entry['kind'] ?? null) !== $kind || !is_array($entry['payload'] ?? null)) {
            return null;
        }
        return $entry['payload'];
    }

    private function purge(): void
    {
        $entries = $_SESSION[self::SESSION_KEY] ?? [];
        if (!is_array($entries)) {
            $_SESSION[self::SESSION_KEY] = [];
            return;
        }
        $minimum = time() - self::TTL;
        foreach ($entries as $token => $entry) {
            if (!is_array($entry) || (int) ($entry['created_at'] ?? 0) < $minimum) {
                unset($entries[$token]);
            }
        }
        $_SESSION[self::SESSION_KEY] = $entries;
    }

    private function trim(): void
    {
        $entries = $_SESSION[self::SESSION_KEY] ?? [];
        if (!is_array($entries) || count($entries) <= self::MAX_ENTRIES) {
            return;
        }
        uasort($entries, static fn (array $a, array $b): int => (int) ($a['created_at'] ?? 0) <=> (int) ($b['created_at'] ?? 0));
        while (count($entries) > self::MAX_ENTRIES) {
            array_shift($entries);
        }
        $_SESSION[self::SESSION_KEY] = $entries;
    }
}
