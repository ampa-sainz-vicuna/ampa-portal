<?php

declare(strict_types=1);

namespace App\Portal\Application\Calendar;

use App\Portal\Domain\Calendar\CalendarError;
use App\Portal\Domain\Calendar\CalendarPeriod;
use App\Portal\Domain\Calendar\SchoolYear;
use App\Portal\Domain\Calendar\SchoolYearRepository;

/**
 * Guarda el calendario de un curso entero: si no existía se crea, y si
 * existía se sustituye (la pantalla manda siempre el curso completo).
 */
final readonly class DefineSchoolYear
{
    public function __construct(private SchoolYearRepository $schoolYears)
    {
    }

    /**
     * @param list<CalendarPeriod> $periods
     *
     * @throws CalendarError
     */
    public function __invoke(string $code, string $classesStart, string $classesEnd, array $periods): SchoolYear
    {
        $schoolYear = $this->schoolYears->find($code);

        if (null === $schoolYear) {
            $schoolYear = SchoolYear::define($code, $classesStart, $classesEnd, $periods);
        } else {
            $schoolYear->redefine($classesStart, $classesEnd, $periods);
        }

        $this->schoolYears->save($schoolYear);

        return $schoolYear;
    }
}
