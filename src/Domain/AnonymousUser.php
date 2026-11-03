<?php
declare(strict_types=1);

namespace Portix\Domain;

final class AnonymousUser
{
    public function pseudo(): string { return ''; }
    public function passwordHash(): string { return ''; }
    public function email(): string { return ''; }
    public function lastName(): string { return ''; }
    public function firstName(): string { return ''; }
    public function isAdmin(): bool { return false; }
    public function isRedactor(): bool { return false; }
    public function verifyPassword(string $plainText): bool { return false; }
    public function passwordNeedsUpgrade(): bool { return false; }
}
