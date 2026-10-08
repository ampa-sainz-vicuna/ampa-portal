<?php

declare(strict_types=1);

namespace App\Portal\Domain\Board;

/**
 * Los cargos de la junta del AMPA.
 *
 * Presidencia, vicepresidencia, secretaría y tesorería tienen un solo titular
 * a la vez; de vocales, los que hagan falta. La regla se hace cumplir en el
 * caso de uso (para dar un mensaje claro) y en la base de datos (índice único
 * parcial, para que ni dos peticiones simultáneas la rompan).
 *
 * El orden de las constantes es el de la pantalla: primero los cargos con
 * titular único.
 */
enum BoardPosition: string
{
    case President = 'president';
    case VicePresident = 'vice_president';
    case Secretary = 'secretary';
    case Treasurer = 'treasurer';
    case Member = 'member';

    /** Si como mucho una persona activa puede tenerlo a la vez. */
    public function hasSingleHolder(): bool
    {
        return self::Member !== $this;
    }

    /** Para ordenar la lista: presidencia primero, vocales al final. */
    public function rank(): int
    {
        foreach (self::cases() as $index => $case) {
            if ($case === $this) {
                return $index;
            }
        }

        throw new \LogicException('Cargo desconocido.');
    }

    public static function fromString(string $value): self
    {
        return self::tryFrom($value) ?? throw new \InvalidArgumentException('El cargo no es válido.');
    }
}
