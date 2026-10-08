<?php

declare(strict_types=1);

namespace App\Portal\Domain\Board;

/**
 * Un teléfono de contacto: 9 dígitos (España) o internacional con "+" y entre
 * 8 y 15 dígitos (el máximo del plan E.164 es 15). Se guarda sin espacios,
 * puntos, guiones ni paréntesis.
 *
 * Validación a propósito sencilla: no se comprueba que el número exista, solo
 * que no sea un error de teclado evidente.
 */
final readonly class PhoneNumber
{
    private function __construct(private string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $normalized = (string) preg_replace('/[\s.\-()]+/u', '', $value);

        if (1 !== preg_match('/^(\d{9}|\+\d{8,15})$/', $normalized)) {
            throw new \InvalidArgumentException('El teléfono no es válido: 9 cifras, o con prefijo internacional (+34…).');
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
