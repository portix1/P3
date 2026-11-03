<?php
declare(strict_types=1);

namespace Portix\Support;

use Throwable;

final class Translator
{
    /** @param array<string, mixed> $messages */
    public function __construct(private readonly array $messages)
    {
    }

    public function text(string $key, ?string $fallback = null): string
    {
        $message = $this->messages[$key] ?? null;
        return is_scalar($message) ? (string) $message : ($fallback ?? $key);
    }

    public function exception(Throwable $error): string
    {
        $message = $error->getMessage();
        if (isset($this->messages[$message]) && is_scalar($this->messages[$message])) {
            return (string) $this->messages[$message];
        }

        $key = match ($message) {
            'Cette action exige une requête POST.' => 'security_post_required',
            'Jeton de formulaire invalide. Rechargez la page.' => 'security_invalid_csrf',
            'Accès refusé.' => 'security_access_denied',
            default => '',
        };
        return $key === '' ? $message : $this->text($key, $message);
    }
}
