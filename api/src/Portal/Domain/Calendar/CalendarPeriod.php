<?php

declare(strict_types=1);

namespace App\Portal\Domain\Calendar;

/**
 * Uno o varios días seguidos que no son normales: "Navidad, del 23/12 al
 * 7/1, no lectivo" o "Día de la Hispanidad, el 12/10, festivo". Un día
 * suelto es un periodo que empieza y acaba el mismo día.
 *
 * Las fechas, como texto AAAA-MM-DD: son días del calendario, sin hora ni
 * zona, y así se comparan sin sorpresas.
 */
final readonly class CalendarPeriod
{
    private function __construct(
        private string $from,
        private string $to,
        private DayKind $kind,
        private string $name,
    ) {
    }

    /**
     * @throws CalendarError
     */
    public static function of(string $from, string $to, DayKind $kind, string $name): self
    {
        $name = trim($name);

        if ('' === $name) {
            throw new CalendarError('Cada día sin clase necesita un nombre (Navidad, Día de la Hispanidad…).');
        }

        if (mb_strlen($name) > 120) {
            throw new CalendarError(sprintf('"%s…" es demasiado largo (120 caracteres como mucho).', mb_substr($name, 0, 30)));
        }

        $from = self::date($from, $name);
        $to = self::date($to, $name);

        if ($to < $from) {
            throw new CalendarError(sprintf('"%s" acaba antes de empezar.', $name));
        }

        return new self($from, $to, $kind, $name);
    }

    /**
     * @param array{from: string, to: string, kind: string, name: string} $data
     */
    public static function fromArray(array $data): self
    {
        return self::of($data['from'], $data['to'], DayKind::from($data['kind']), $data['name']);
    }

    /** @return array{from: string, to: string, kind: string, name: string} */
    public function toArray(): array
    {
        return ['from' => $this->from, 'to' => $this->to, 'kind' => $this->kind->value, 'name' => $this->name];
    }

    public function covers(string $day): bool
    {
        return $day >= $this->from && $day <= $this->to;
    }

    public function getFrom(): string
    {
        return $this->from;
    }

    public function getTo(): string
    {
        return $this->to;
    }

    public function getKind(): DayKind
    {
        return $this->kind;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @throws CalendarError
     */
    public static function date(string $value, string $what): string
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        if (false === $date || $date->format('Y-m-d') !== $value) {
            throw new CalendarError(sprintf('"%s": la fecha "%s" no vale (tiene que ser AAAA-MM-DD).', $what, $value));
        }

        return $value;
    }
}
