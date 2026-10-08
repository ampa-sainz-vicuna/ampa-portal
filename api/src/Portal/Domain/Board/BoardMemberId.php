<?php

declare(strict_types=1);

namespace App\Portal\Domain\Board;

use Symfony\Component\Uid\Uuid;

/**
 * El identificador de un cargo de la junta (una persona en un cargo durante un
 * periodo). Va en las URL de la API en lugar de datos personales.
 */
final readonly class BoardMemberId
{
    private function __construct(private string $value)
    {
    }

    public static function generate(): self
    {
        return new self(Uuid::v7()->toRfc4122());
    }

    public static function fromString(string $value): self
    {
        if (!Uuid::isValid($value)) {
            throw new \InvalidArgumentException('El identificador del cargo no es válido.');
        }

        return new self(strtolower($value));
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /** Doctrine lo necesita (ver UserId::__toString). */
    public function __toString(): string
    {
        return $this->value;
    }
}
