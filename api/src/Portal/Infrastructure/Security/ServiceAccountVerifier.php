<?php

declare(strict_types=1);

namespace App\Portal\Infrastructure\Security;

/**
 * Comprueba que una llamada viene del SERVIDOR de una aplicación de la suite
 * cuando no hay ninguna persona detrás (el resumen diario de tareas, a las
 * 5:00): el token de identidad que Google firma para la cuenta de servicio con
 * la que corre, pedido al servidor de metadatos de Cloud Run.
 *
 * Una interfaz, como IdentityVerifier, porque en los tests no hay forma de
 * fabricar un token firmado por Google.
 */
interface ServiceAccountVerifier
{
    /**
     * @return string el correo de la cuenta de servicio
     *
     * @throws InvalidIdentity si el token no vale, ha caducado, no es para el
     *                         portal o la cuenta no es de la suite
     */
    public function verify(string $token): string;
}
