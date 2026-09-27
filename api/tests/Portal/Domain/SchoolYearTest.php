<?php

declare(strict_types=1);

namespace App\Tests\Portal\Domain;

use App\Portal\Domain\Calendar\CalendarError;
use App\Portal\Domain\Calendar\CalendarPeriod;
use App\Portal\Domain\Calendar\DayKind;
use App\Portal\Domain\Calendar\SchoolYear;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SchoolYearTest extends TestCase
{
    #[Test]
    public function un_dia_de_clase_es_de_lunes_a_viernes_dentro_del_curso_y_sin_nada_apuntado(): void
    {
        $year = $this->year();

        self::assertTrue($year->isSchoolDay(new \DateTimeImmutable('2026-10-13')), 'martes normal');
        self::assertFalse($year->isSchoolDay(new \DateTimeImmutable('2026-10-12')), 'festivo');
        self::assertFalse($year->isSchoolDay(new \DateTimeImmutable('2026-12-28')), 'vacaciones de Navidad');
        self::assertFalse($year->isSchoolDay(new \DateTimeImmutable('2026-10-17')), 'sábado');
        self::assertFalse($year->isSchoolDay(new \DateTimeImmutable('2026-09-07')), 'antes de empezar las clases');
        self::assertFalse($year->isSchoolDay(new \DateTimeImmutable('2027-06-21')), 'después de acabar');
    }

    #[Test]
    public function festivo_es_solo_lo_apuntado_como_festivo_no_lo_no_lectivo(): void
    {
        $year = $this->year();

        self::assertTrue($year->isHoliday(new \DateTimeImmutable('2026-10-12')));
        // En Navidad no hay clase, pero Alberto trabaja.
        self::assertFalse($year->isHoliday(new \DateTimeImmutable('2026-12-28')));
        // Un festivo de verano, fuera de las clases, también cuenta.
        self::assertTrue($year->isHoliday(new \DateTimeImmutable('2027-08-15')));
    }

    #[Test]
    public function los_periodos_salen_por_fecha_aunque_se_apunten_desordenados(): void
    {
        self::assertSame(
            ['Día de la Hispanidad', 'Navidad', 'Asunción'],
            array_map(static fn (CalendarPeriod $period): string => $period->getName(), $this->year()->getPeriods()),
        );
    }

    #[Test]
    public function redefinirlo_sustituye_las_fechas_y_los_periodos(): void
    {
        $year = $this->year();

        $year->redefine('2026-09-09', '2027-06-22', []);

        self::assertSame('2026-09-09', $year->getClassesStart());
        self::assertSame([], $year->getPeriods());
        self::assertTrue($year->isSchoolDay(new \DateTimeImmutable('2026-10-12')));
    }

    #[Test]
    #[DataProvider('days')]
    public function sabe_de_que_curso_es_cada_dia(string $day, string $code): void
    {
        self::assertSame($code, SchoolYear::codeFor(new \DateTimeImmutable($day)));
    }

    /** @return iterable<string, array{string, string}> */
    public static function days(): iterable
    {
        yield 'el 1 de septiembre empieza el curso' => ['2026-09-01', '2026-2027'];
        yield 'el 31 de agosto aún es del anterior' => ['2026-08-31', '2025-2026'];
        yield 'enero' => ['2027-01-15', '2026-2027'];
    }

    #[Test]
    #[DataProvider('invalid')]
    public function no_admite_un_curso_imposible(string $code, string $start, string $end, string $expected): void
    {
        $this->expectException(CalendarError::class);
        $this->expectExceptionMessage($expected);

        SchoolYear::define($code, $start, $end, []);
    }

    /** @return iterable<string, array{string, string, string, string}> */
    public static function invalid(): iterable
    {
        yield 'el nombre del curso' => ['2026-2028', '2026-09-08', '2027-06-18', 'no es un curso'];
        yield 'una fecha que no existe' => ['2026-2027', '2026-09-31', '2027-06-18', 'no vale'];
        yield 'clases fuera del curso' => ['2026-2027', '2026-08-31', '2027-06-18', 'tienen que estar entre'];
        yield 'al revés' => ['2026-2027', '2027-06-18', '2026-09-08', 'acaban antes de empezar'];
    }

    #[Test]
    public function un_periodo_fuera_del_curso_no_vale(): void
    {
        $this->expectException(CalendarError::class);
        $this->expectExceptionMessage('"Reyes" no cae dentro del curso 2026-2027');

        SchoolYear::define('2026-2027', '2026-09-08', '2027-06-18', [
            CalendarPeriod::of('2026-01-06', '2026-01-06', DayKind::Holiday, 'Reyes'),
        ]);
    }

    #[Test]
    public function un_periodo_sin_nombre_o_del_reves_no_vale(): void
    {
        try {
            CalendarPeriod::of('2026-10-12', '2026-10-12', DayKind::Holiday, '  ');
            self::fail('Sin nombre no debería valer.');
        } catch (CalendarError) {
        }

        $this->expectExceptionMessage('"Navidad" acaba antes de empezar.');

        CalendarPeriod::of('2027-01-07', '2026-12-23', DayKind::NonSchool, 'Navidad');
    }

    private function year(): SchoolYear
    {
        return SchoolYear::define('2026-2027', '2026-09-08', '2027-06-18', [
            CalendarPeriod::of('2027-08-15', '2027-08-15', DayKind::Holiday, 'Asunción'),
            CalendarPeriod::of('2026-12-23', '2027-01-07', DayKind::NonSchool, 'Navidad'),
            CalendarPeriod::of('2026-10-12', '2026-10-12', DayKind::Holiday, 'Día de la Hispanidad'),
        ]);
    }
}
