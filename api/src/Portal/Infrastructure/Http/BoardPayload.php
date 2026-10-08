<?php

declare(strict_types=1);

namespace App\Portal\Infrastructure\Http;

use App\Portal\Application\Board\BoardMemberData;
use App\Portal\Domain\Board\BoardPosition;
use App\Portal\Domain\Board\IdentityDocument;
use App\Portal\Domain\Board\PhoneNumber;
use App\Portal\Domain\User\EmailAddress;

/**
 * Lo que manda la pantalla *Junta* al dar de alta o editar un cargo:
 * {"firstName", "lastName", "document", "address", "email", "phone",
 *  "position", "startDate": "AAAA-MM-DD"}.
 *
 * Dos tipos de fallo, que el controlador traduce distinto:
 *  - UnexpectedValueException: falta un campo o no es texto (400);
 *  - InvalidArgumentException: el valor no vale (422). Sus mensajes no llevan
 *    el DNI ni la dirección (ver IdentityDocument).
 */
final class BoardPayload
{
    private const array FIELDS = ['firstName', 'lastName', 'document', 'address', 'email', 'phone', 'position', 'startDate'];

    /** @param array<string, mixed> $payload */
    public static function parse(array $payload): BoardMemberData
    {
        foreach (self::FIELDS as $field) {
            if (!is_string($payload[$field] ?? null)) {
                throw new \UnexpectedValueException(sprintf('Falta el campo "%s" o no es un texto.', $field));
            }
        }

        return new BoardMemberData(
            $payload['firstName'],
            $payload['lastName'],
            IdentityDocument::fromString($payload['document']),
            $payload['address'],
            EmailAddress::fromString($payload['email']),
            PhoneNumber::fromString($payload['phone']),
            BoardPosition::fromString($payload['position']),
            self::date($payload['startDate'], 'La fecha de inicio'),
        );
    }

    /** AAAA-MM-DD estricto: "2026-02-31" no vale. */
    public static function date(string $value, string $label): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        if (false === $date || $date->format('Y-m-d') !== $value) {
            throw new \InvalidArgumentException(sprintf('%s no es válida (formato AAAA-MM-DD).', $label));
        }

        return $date;
    }
}
