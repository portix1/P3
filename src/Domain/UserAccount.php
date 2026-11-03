<?php
declare(strict_types=1);

namespace Portix\Domain;

final class UserAccount
{
    /** @param array<string, mixed> $row */
    public function __construct(private readonly array $row)
    {
    }

    public function pseudo(): string
    {
        return (string) $this->row['pseudo'];
    }

    public function passwordHash(): string
    {
        return (string) $this->row['password_hash'];
    }

    public function email(): string
    {
        return (string) ($this->row['email'] ?? '');
    }

    public function lastName(): string
    {
        return (string) ($this->row['last_name'] ?? '');
    }

    public function firstName(): string
    {
        return (string) ($this->row['first_name'] ?? '');
    }

    public function isAdmin(): bool
    {
        return (bool) $this->row['is_admin'];
    }

    public function isRedactor(): bool
    {
        return (bool) $this->row['is_redactor'];
    }

    public function articlePermissions(): array
    {
        return ArticlePermissions::decode((string) ($this->row['article_permissions'] ?? '{}'));
    }

    public function authVersion(): int
    {
        return max(1, (int) ($this->row['auth_version'] ?? 1));
    }

    public function verifyPassword(string $plainText): bool
    {
        $stored = $this->passwordHash();
        if ($stored === '') {
            return false;
        }
        if (preg_match('/^[a-f0-9]{32}$/i', $stored) === 1) {
            return hash_equals(strtolower($stored), md5($plainText));
        }
        return password_verify($plainText, $stored);
    }

    public function passwordNeedsUpgrade(): bool
    {
        return preg_match('/^[a-f0-9]{32}$/i', $this->passwordHash()) === 1
            || password_needs_rehash($this->passwordHash(), PASSWORD_DEFAULT);
    }
}
