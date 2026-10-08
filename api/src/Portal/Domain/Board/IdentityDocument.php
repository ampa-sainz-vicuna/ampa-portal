<?php

declare(strict_types=1);

namespace App\Portal\Domain\Board;

/**
 * Un DNI o un NIE español, normalizado: mayúsculas, sin espacios, puntos ni
 * guiones ("12.345.678-z" y "12345678Z" son el mismo).
 *
 * Se comprueba la letra de control, que es lo que más se confunde al teclear.
 * Es un dato personal: el mensaje de error NUNCA lleva el valor, para que no
 * acabe en el registro ni en una respuesta.
 */
final readonly class IdentityDocument
{
    private const string LETTERS = 'TRWAGMYFPDXBNJZSQVHLCKE';

    private function __construct(private string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $normalized = strtoupper((string) preg_replace('/[\s.\-]+/u', '', $value));

        // El NIE empieza por X, Y o Z, que valen 0, 1 y 2 para calcular la letra.
        $digits = strtr(substr($normalized, 0, 1), ['X' => '0', 'Y' => '1', 'Z' => '2']).substr($normalized, 1, 7);

        if (1 !== preg_match('/^(\d{8}|[XYZ]\d{7})[A-Z]$/', $normalized)
            || self::LETTERS[(int) $digits % 23] !== substr($normalized, -1)) {
            throw new \InvalidArgumentException('El DNI o NIE no es válido (revisa los números y la letra).');
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
