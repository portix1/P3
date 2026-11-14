<?php
declare(strict_types=1);

namespace Portix\Preview;

/**
 * Short-lived previews stored inside the authenticated PHP session.
 * Tokens are bound to the authenticated account and its permission version.
 */
final class SessionPreviewStore
{
    private const SESSION_KEY = 'portix_content_previews';
    private const TTL = 1800;
    private const MAX_ENTRIES = 20;

    /** @param array<string, mixed> $payload */
    public function create(string $kind, array $payload): string
    {
        $identity = self::identity();
        if ($identity === null) { throw new \RuntimeException('Accès refusé.'); }
        $this->purge();
        $token = bin2hex(random_bytes(16));
        $_SESSION[self::SESSION_KEY][$token] = [
            'identity' => $identity,
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
        $identity = self::identity();
        if ($identity === null || preg_match('/^[a-f0-9]{32}$/D', $token) !== 1) {
            return null;
        }
        $this->purge();
        $entry = $_SESSION[self::SESSION_KEY][$token] ?? null;
        if (!is_array($entry) || ($entry['identity'] ?? null) !== $identity || ($entry['kind'] ?? null) !== $kind || !is_array($entry['payload'] ?? null)) {
            return null;
        }
        return $entry['payload'];
    }

    public static function clear(): void
    {
        unset($_SESSION[self::SESSION_KEY]);
    }

    private static function identity(): ?array
    {
        $name = $_SESSION['user_nick'] ?? null;
        $version = $_SESSION['auth_version'] ?? null;
        if (($_SESSION['authenticated'] ?? false) !== true || !is_string($name) || $name === '' || !is_int($version) || $version < 1) { return null; }
        return [$name, $version];
    }

    private function purge(): void
    {
        $entries = $_SESSION[self::SESSION_KEY] ?? [];
        if (!is_array($entries)) {
            $_SESSION[self::SESSION_KEY] = [];
            return;
        }
        $minimum = time() - self::TTL;
        $identity = self::identity();
        foreach ($entries as $token => $entry) {
            if (!is_array($entry) || $identity === null || ($entry['identity'] ?? null) !== $identity || (int) ($entry['created_at'] ?? 0) < $minimum) {
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
