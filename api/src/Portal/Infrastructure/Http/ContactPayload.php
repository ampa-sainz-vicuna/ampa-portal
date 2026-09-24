<?php

declare(strict_types=1);

namespace App\Portal\Infrastructure\Http;

use App\Portal\Domain\User\EmailAddress;
use App\Portal\Domain\User\NotificationTarget;

/**
 * Lee del JSON el segundo correo y a dónde van los avisos, que llegan igual
 * desde la ficha de la pantalla de permisos y desde "mis correos":
 *
 *   {"secondaryEmail": "…" | null, "notify": "primary" | "secondary" | "both"}
 *
 * Los dos son opcionales: sin ellos, sin segundo correo y avisos al principal.
 */
final class ContactPayload
{
    /**
     * @param array<string, mixed> $payload
     *
     * @return array{?EmailAddress, NotificationTarget}
     *
     * @throws \InvalidArgumentException si alguno no vale
     */
    public static function parse(array $payload): array
    {
        $secondary = $payload['secondaryEmail'] ?? null;
        $notify = $payload['notify'] ?? NotificationTarget::Primary->value;

        if (null !== $secondary && !is_string($secondary)) {
            throw new \InvalidArgumentException('El segundo correo ("secondaryEmail") tiene que ser un texto o null.');
        }

        $target = is_string($notify) ? NotificationTarget::tryFrom($notify) : null;
        if (null === $target) {
            throw new \InvalidArgumentException('"notify" tiene que ser "primary", "secondary" o "both".');
        }

        // Un campo vacío en la pantalla es "sin segundo correo".
        $secondary = null === $secondary || '' === trim($secondary) ? null : EmailAddress::fromString($secondary);

        return [$secondary, $target];
    }
}
