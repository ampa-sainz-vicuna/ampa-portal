<?php

declare(strict_types=1);

namespace App\Portal\Infrastructure\Http;

use App\Portal\Domain\Calendar\CalendarPeriod;
use App\Portal\Domain\Calendar\SchoolYear;

/**
 * Un curso en JSON, igual para las aplicaciones (/api/calendario) y para la
 * pantalla (/api/admin/calendario): es el contrato con el cliente 0.1.5.
 *
 *   {"schoolYear": "2026-2027", "classesStart": "2026-09-08", "classesEnd": "2027-06-18",
 *    "periods": [{"from": "2026-10-12", "to": "2026-10-12", "kind": "holiday", "name": "Día de la Hispanidad"}]}
 */
final class SchoolYearPresenter
{
    /**
     * @return array{schoolYear: string, classesStart: string, classesEnd: string, periods: list<array{from: string, to: string, kind: string, name: string}>}
     */
    public function __invoke(SchoolYear $schoolYear): array
    {
        return [
            'schoolYear' => $schoolYear->getCode(),
            'classesStart' => $schoolYear->getClassesStart(),
            'classesEnd' => $schoolYear->getClassesEnd(),
            'periods' => array_map(static fn (CalendarPeriod $period): array => $period->toArray(), $schoolYear->getPeriods()),
        ];
    }
}
