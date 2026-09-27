<?php

declare(strict_types=1);

namespace App\Portal\Application\Heartbeat;

/**
 * El cómputo gastado en lo que va de periodo, en CU-horas (lo que cuenta
 * Neon: tamaño de la máquina × horas encendida), frente a lo que da el plan.
 */
final readonly class ComputeUsage
{
    public function __construct(
        private float $hoursUsed,
        private float $hoursAllowed,
        private ?\DateTimeImmutable $periodEnd = null,
    ) {
        if ($hoursAllowed <= 0) {
            throw new \InvalidArgumentException('El cómputo del plan tiene que ser mayor que cero.');
        }
    }

    public function getHoursUsed(): float
    {
        return $this->hoursUsed;
    }

    public function getHoursAllowed(): float
    {
        return $this->hoursAllowed;
    }

    public function getPeriodEnd(): ?\DateTimeImmutable
    {
        return $this->periodEnd;
    }

    /** De 0 a 1 (o más, si se ha pasado). */
    public function share(): float
    {
        return $this->hoursUsed / $this->hoursAllowed;
    }

    /** "12,4 de 100 CU-horas; el periodo acaba el 01/10/2026". */
    public function describe(): string
    {
        $text = sprintf(
            '%s de %s CU-horas',
            number_format($this->hoursUsed, 1, ',', '.'),
            number_format($this->hoursAllowed, 0, ',', '.'),
        );

        if (null !== $this->periodEnd) {
            $text .= '; el periodo acaba el '.$this->periodEnd->setTimezone(new \DateTimeZone('Europe/Madrid'))->format('d/m/Y');
        }

        return $text;
    }
}
