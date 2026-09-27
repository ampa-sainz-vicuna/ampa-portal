<?php

declare(strict_types=1);

namespace App\Portal\Domain\Calendar;

/** Un curso que no se puede guardar así: el mensaje es para la pantalla. */
final class CalendarError extends \DomainException
{
}
