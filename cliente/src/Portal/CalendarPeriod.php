<?php

declare(strict_types=1);

namespace Ampa\PortalCliente\Portal;

/**
 * Uno o varios días seguidos sin clase en el calendario escolar común
 * (0.1.5): "Navidad, del 2026-12-23 al 2027-01-07, no lectivo". Las fechas,
 * AAAA-MM-DD.
 */
final readonly class CalendarPeriod
{
    /** Ni hay clase ni se trabaja (lo que mira fichajes). */
    public const string HOLIDAY = 'holiday';

    /** No hay clase, pero es laborable (vacaciones escolares, días no lectivos del colegio). */
    public const string NON_SCHOOL = 'non_school';

    public function __construct(
        private string $from,
        private string $to,
        private string $kind,
        private string $name,
    ) {
    }

    public function getFrom(): string
    {
        return $this->from;
    }

    public function getTo(): string
    {
        return $this->to;
    }

    /** CalendarPeriod::HOLIDAY o CalendarPeriod::NON_SCHOOL. */
    public function getKind(): string
    {
        return $this->kind;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isHoliday(): bool
    {
        return self::HOLIDAY === $this->kind;
    }

    public function covers(string $day): bool
    {
        return $day >= $this->from && $day <= $this->to;
    }
}
