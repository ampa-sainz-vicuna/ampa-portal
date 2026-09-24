<?php

declare(strict_types=1);

namespace App\Portal\Domain\User;

use Symfony\Component\Uid\Uuid;

/**
 * El identificador de una persona en el portal.
 *
 * Existe aparte del correo para que la pantalla de permisos pueda referirse a
 * alguien por una URL que no lleva datos personales (`/api/admin/users/{id}`).
 */
final readonly class UserId
{
    private function __construct(private string $value)
    {
    }

    public static function generate(): self
    {
        // v7: ordenado por fecha de creación, así el índice crece por el final.
        return new self(Uuid::v7()->toRfc4122());
    }

    public static function fromString(string $value): self
    {
        if (!Uuid::isValid($value)) {
            throw new \InvalidArgumentException(sprintf('"%s" no es un identificador válido.', $value));
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

    /**
     * Doctrine lo necesita: guarda las entidades cargadas en un mapa cuya
     * clave es el identificador convertido en texto. Sin esto, guardar una
     * persona falla con "could not be converted to string".
     */
    public function __toString(): string
    {
        return $this->value;
    }
}
