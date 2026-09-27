<?php

declare(strict_types=1);

namespace Ampa\PortalCliente\Portal;

/**
 * El calendario de un curso, tal como lo cargó el portal (0.1.5): cuándo hay
 * clase y qué días son festivos. Las mismas reglas que en el portal:
 *
 * - día de clase: dentro de las clases, de lunes a viernes y sin nada
 *   apuntado ese día;
 * - festivo: apuntado como festivo. Los fines de semana no se apuntan: eso ya
 *   lo sabe cada aplicación.
 *
 * Se pide con SuiteCalendar.
 */
final readonly class SchoolCalendar
{
    /**
     * @param string               $schoolYear   "2026-2027"
     * @param string               $classesStart AAAA-MM-DD, el primer día de clase
     * @param string               $classesEnd   AAAA-MM-DD, el último
     * @param list<CalendarPeriod> $periods      por fecha
     */
    public function __construct(
        private string $schoolYear,
        private string $classesStart,
        private string $classesEnd,
        private array $periods,
    ) {
    }

    public function getSchoolYear(): string
    {
        return $this->schoolYear;
    }

    public function getClassesStart(): string
    {
        return $this->classesStart;
    }

    public function getClassesEnd(): string
    {
        return $this->classesEnd;
    }

    /** @return list<CalendarPeriod> */
    public function getPeriods(): array
    {
        return $this->periods;
    }

    public function isSchoolDay(\DateTimeInterface $day): bool
    {
        $date = $day->format('Y-m-d');

        if ($date < $this->classesStart || $date > $this->classesEnd || (int) $day->format('N') > 5) {
            return false;
        }

        foreach ($this->periods as $period) {
            if ($period->covers($date)) {
                return false;
            }
        }

        return true;
    }

    public function isHoliday(\DateTimeInterface $day): bool
    {
        $date = $day->format('Y-m-d');

        foreach ($this->periods as $period) {
            if ($period->isHoliday() && $period->covers($date)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Los días de clase entre dos fechas, las dos incluidas. Por ejemplo, los
     * de un mes, para pagar los desayunos por alumno y día lectivo.
     *
     * @return list<\DateTimeImmutable>
     */
    public function schoolDaysBetween(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        $days = [];
        $day = \DateTimeImmutable::createFromInterface($from)->setTime(0, 0);
        $last = $to->format('Y-m-d');

        while ($day->format('Y-m-d') <= $last) {
            if ($this->isSchoolDay($day)) {
                $days[] = $day;
            }
            $day = $day->modify('+1 day');
        }

        return $days;
    }

    /** "2026-2027" para cualquier día entre el 1/9/2026 y el 31/8/2027. */
    public static function schoolYearOf(\DateTimeInterface $day): string
    {
        $year = (int) $day->format('Y');
        $start = (int) $day->format('n') >= 9 ? $year : $year - 1;

        return sprintf('%d-%d', $start, $start + 1);
    }
}
