<?php

declare(strict_types=1);

namespace App\Portal\Domain\Help;

/** Un fichero de preguntas que no se puede cargar así: el mensaje es para la pantalla. */
final class FaqError extends \DomainException
{
}
