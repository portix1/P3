<?php
declare(strict_types=1);

namespace Portix\View;

final class Html
{
    private function __construct(private readonly string $value)
    {
    }

    public static function trusted(string $value): self
    {
        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
