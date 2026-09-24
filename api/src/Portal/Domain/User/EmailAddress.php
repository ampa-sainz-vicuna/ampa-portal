<?php

declare(strict_types=1);

namespace App\Portal\Domain\User;

/**
 * Un correo, siempre en minúsculas.
 *
 * Es lo que identifica a una persona en toda la suite: lo que devuelve Google,
 * lo que viaja dentro del token y lo que se busca en cada petición. Por eso se
 * normaliza al crearlo: "Admin@AMPA…" y "admin@ampa…" son la misma persona, y
 * si no lo fueran para la base de datos, quien se dio de alta con mayúsculas
 * no podría entrar.
 *
 * No se exige el dominio del AMPA: tareas admitirá cuentas de Google
 * personales. Quién entra lo deciden los permisos, no el dominio.
 */
final readonly class EmailAddress
{
    private function __construct(private string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $normalized = mb_strtolower(trim($value));

        if (false === filter_var($normalized, \FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException(sprintf('"%s" no es un correo válido.', $value));
        }

        return new self($normalized);
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
