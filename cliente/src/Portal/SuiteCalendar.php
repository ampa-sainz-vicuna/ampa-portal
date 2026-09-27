<?php

declare(strict_types=1);

namespace Ampa\PortalCliente\Portal;

/**
 * El calendario escolar común, para usar desde un caso de uso (0.1.5):
 *
 *   $calendar->forDay($hoy)?->isHoliday($hoy)        // fichajes: ¿se trabaja?
 *   $calendar->forSchoolYear('2026-2027')            // el curso entero
 *       ?->schoolDaysBetween($uno, $treinta)          // facturación: días de desayuno
 *
 * Null si el curso no está cargado en el portal: la aplicación decide qué
 * hacer (fichajes, seguir con sus festivos; facturación, no calcular).
 *
 * Como SuiteMembers, pregunta con el token de quien hace la petición y, sin
 * nadie detrás (el latido), con el de este servidor. Cada curso se pregunta
 * una vez por petición.
 */
final class SuiteCalendar
{
    /** @var array<string, SchoolCalendar|null> */
    private array $loaded = [];

    public function __construct(
        private readonly Portal $portal,
        private readonly CallerToken $token,
    ) {
    }

    /**
     * @throws SessionRejected   si el portal no acepta el token
     * @throws PortalUnavailable si el portal no contesta
     */
    public function forSchoolYear(string $schoolYear): ?SchoolCalendar
    {
        if (!array_key_exists($schoolYear, $this->loaded)) {
            $this->loaded[$schoolYear] = $this->portal->calendar($this->token->current(), $schoolYear);
        }

        return $this->loaded[$schoolYear];
    }

    /**
     * El curso al que pertenece ese día (del 1 de septiembre al 31 de agosto).
     *
     * @throws SessionRejected
     * @throws PortalUnavailable
     */
    public function forDay(\DateTimeInterface $day): ?SchoolCalendar
    {
        return $this->forSchoolYear(SchoolCalendar::schoolYearOf($day));
    }
}
