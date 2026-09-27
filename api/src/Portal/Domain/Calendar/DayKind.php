<?php

declare(strict_types=1);

namespace App\Portal\Domain\Calendar;

/**
 * Qué pasa un día que no es normal. Los fines de semana no se apuntan: cada
 * aplicación ya sabe que el sábado no hay clase.
 */
enum DayKind: string
{
    /** Festivo: ni hay clase ni se trabaja (12 de octubre, fiesta local…). Lo usa fichajes. */
    case Holiday = 'holiday';

    /**
     * No lectivo: no hay clase, pero es laborable (vacaciones de Navidad y de
     * Semana Santa, días no lectivos del colegio). No hay desayunos ni
     * extraescolares, pero Alberto trabaja.
     */
    case NonSchool = 'non_school';
}
