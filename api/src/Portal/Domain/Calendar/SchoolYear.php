<?php

declare(strict_types=1);

namespace App\Portal\Domain\Calendar;

/**
 * El calendario de un curso, común a toda la suite: cuándo empiezan y acaban
 * las clases y qué días no son normales (festivos y no lectivos).
 *
 * Existe para no apuntar lo mismo en tres sitios: fichajes necesita los
 * festivos (hasta ahora se metían a mano, uno a uno), facturación los días
 * lectivos (Cutasa cobra los desayunos por alumno y día lectivo) y listados
 * los necesitará para los desayunos sueltos. Lo carga quien gestiona los
 * permisos, una vez por curso, en el portal; las aplicaciones lo piden con el
 * cliente (SuiteCalendar, 0.1.5).
 *
 * Un curso va del 1 de septiembre al 31 de agosto y se llama por sus dos
 * años: "2026-2027". Los días de fuera de las clases (julio, agosto) también
 * pueden tener festivos: Alberto trabaja en julio.
 */
final class SchoolYear
{
    /** @var list<array{from: string, to: string, kind: string, name: string}> */
    private array $periods;

    /**
     * @param list<CalendarPeriod> $periods
     */
    private function __construct(
        private string $code,
        private string $classesStart,
        private string $classesEnd,
        array $periods,
    ) {
        $this->periods = self::checked($code, $classesStart, $classesEnd, $periods);
    }

    /**
     * @param list<CalendarPeriod> $periods
     *
     * @throws CalendarError
     */
    public static function define(string $code, string $classesStart, string $classesEnd, array $periods): self
    {
        return new self(self::checkedCode($code), $classesStart, $classesEnd, $periods);
    }

    /**
     * @param list<CalendarPeriod> $periods
     *
     * @throws CalendarError
     */
    public function redefine(string $classesStart, string $classesEnd, array $periods): void
    {
        $this->periods = self::checked($this->code, $classesStart, $classesEnd, $periods);
        $this->classesStart = $classesStart;
        $this->classesEnd = $classesEnd;
    }

    /** "2026-2027" para cualquier día entre el 1/9/2026 y el 31/8/2027. */
    public static function codeFor(\DateTimeInterface $day): string
    {
        $year = (int) $day->format('Y');
        $start = (int) $day->format('n') >= 9 ? $year : $year - 1;

        return sprintf('%d-%d', $start, $start + 1);
    }

    public function getCode(): string
    {
        return $this->code;
    }

    /** AAAA-MM-DD: el primer día de clase. */
    public function getClassesStart(): string
    {
        return $this->classesStart;
    }

    /** AAAA-MM-DD: el último día de clase. */
    public function getClassesEnd(): string
    {
        return $this->classesEnd;
    }

    /** @return list<CalendarPeriod> por fecha */
    public function getPeriods(): array
    {
        return array_map(CalendarPeriod::fromArray(...), $this->periods);
    }

    /** Si ese día hay clase: dentro del curso, de lunes a viernes y sin nada apuntado. */
    public function isSchoolDay(\DateTimeInterface $day): bool
    {
        $date = $day->format('Y-m-d');

        if ($date < $this->classesStart || $date > $this->classesEnd || (int) $day->format('N') > 5) {
            return false;
        }

        foreach ($this->getPeriods() as $period) {
            if ($period->covers($date)) {
                return false;
            }
        }

        return true;
    }

    /** Si ese día es festivo (no se trabaja). Los fines de semana no cuentan: no se apuntan. */
    public function isHoliday(\DateTimeInterface $day): bool
    {
        $date = $day->format('Y-m-d');

        foreach ($this->getPeriods() as $period) {
            if (DayKind::Holiday === $period->getKind() && $period->covers($date)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @throws CalendarError
     */
    private static function checkedCode(string $code): string
    {
        if (1 !== preg_match('/^(\d{4})-(\d{4})$/', $code, $years) || (int) $years[2] !== (int) $years[1] + 1) {
            throw new CalendarError(sprintf('"%s" no es un curso: tiene que ser como 2026-2027.', $code));
        }

        return $code;
    }

    /**
     * @param list<CalendarPeriod> $periods
     *
     * @return list<array{from: string, to: string, kind: string, name: string}>
     *
     * @throws CalendarError
     */
    private static function checked(string $code, string $classesStart, string $classesEnd, array $periods): array
    {
        $firstYear = (int) substr($code, 0, 4);
        $yearStart = sprintf('%d-09-01', $firstYear);
        $yearEnd = sprintf('%d-08-31', $firstYear + 1);

        CalendarPeriod::date($classesStart, 'Inicio de las clases');
        CalendarPeriod::date($classesEnd, 'Fin de las clases');

        if ($classesStart < $yearStart || $classesEnd > $yearEnd) {
            throw new CalendarError(sprintf('Las clases del curso %s tienen que estar entre el 1/9/%d y el 31/8/%d.', $code, $firstYear, $firstYear + 1));
        }

        if ($classesEnd <= $classesStart) {
            throw new CalendarError('Las clases acaban antes de empezar.');
        }

        foreach ($periods as $period) {
            if ($period->getFrom() < $yearStart || $period->getTo() > $yearEnd) {
                throw new CalendarError(sprintf('"%s" no cae dentro del curso %s (del 1/9/%d al 31/8/%d).', $period->getName(), $code, $firstYear, $firstYear + 1));
            }
        }

        usort($periods, static fn (CalendarPeriod $a, CalendarPeriod $b): int => [$a->getFrom(), $a->getTo()] <=> [$b->getFrom(), $b->getTo()]);

        return array_map(static fn (CalendarPeriod $period): array => $period->toArray(), $periods);
    }
}
