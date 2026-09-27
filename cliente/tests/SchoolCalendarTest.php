<?php

declare(strict_types=1);

namespace Ampa\PortalCliente\Tests;

use Ampa\PortalCliente\Portal\CalendarPeriod;
use Ampa\PortalCliente\Portal\SchoolCalendar;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** Las mismas reglas que el portal (api/tests/Portal/Domain/SchoolYearTest). */
final class SchoolCalendarTest extends TestCase
{
    #[Test]
    public function cuenta_los_dias_de_clase_de_un_mes_para_los_desayunos(): void
    {
        // Octubre de 2026: 22 días de lunes a viernes, menos el 12 (festivo).
        $days = $this->calendar()->schoolDaysBetween(new \DateTimeImmutable('2026-10-01'), new \DateTimeImmutable('2026-10-31'));

        self::assertCount(21, $days);
        self::assertSame('2026-10-01', $days[0]->format('Y-m-d'));
        self::assertNotContains('2026-10-12', array_map(static fn (\DateTimeImmutable $day): string => $day->format('Y-m-d'), $days));
    }

    #[Test]
    public function septiembre_solo_cuenta_desde_el_primer_dia_de_clase(): void
    {
        // Del 8 al 30 de septiembre de 2026: 17 días de lunes a viernes.
        self::assertCount(17, $this->calendar()->schoolDaysBetween(new \DateTimeImmutable('2026-09-01'), new \DateTimeImmutable('2026-09-30')));
    }

    #[Test]
    public function en_navidad_no_hay_clase_pero_no_es_festivo(): void
    {
        $day = new \DateTimeImmutable('2026-12-28');

        self::assertFalse($this->calendar()->isSchoolDay($day));
        self::assertFalse($this->calendar()->isHoliday($day));
    }

    #[Test]
    public function sabe_de_que_curso_es_cada_dia(): void
    {
        self::assertSame('2026-2027', SchoolCalendar::schoolYearOf(new \DateTimeImmutable('2026-09-01')));
        self::assertSame('2025-2026', SchoolCalendar::schoolYearOf(new \DateTimeImmutable('2026-08-31')));
    }

    private function calendar(): SchoolCalendar
    {
        return new SchoolCalendar('2026-2027', '2026-09-08', '2027-06-18', [
            new CalendarPeriod('2026-10-12', '2026-10-12', CalendarPeriod::HOLIDAY, 'Día de la Hispanidad'),
            new CalendarPeriod('2026-12-23', '2027-01-07', CalendarPeriod::NON_SCHOOL, 'Navidad'),
        ]);
    }
}
