<?php

declare(strict_types=1);

namespace Ampa\PortalCliente\Testing;

use Ampa\PortalCliente\Portal\Portal;
use Ampa\PortalCliente\Portal\PortalAccess;
use Ampa\PortalCliente\Portal\Recipient;
use Ampa\PortalCliente\Portal\SessionRejected;

/**
 * El portal, en los tests de una aplicación: sin red y sin portal levantado.
 *
 * El token lleva dentro lo que contestaría el portal. Un test "entra" así:
 *
 *   $client->getCookieJar()->set(new Cookie(
 *       PortalSession::COOKIE,
 *       FakePortal::session('admin@ampasainzvicuna.com', 'Admin', ['usuario']),
 *   ));
 *
 * y los avisos se preparan con FakePortal::recipientsFor('admin', [...]).
 * Se activa en el `when@test` de services.yaml de la aplicación:
 *
 *   Ampa\PortalCliente\Portal\Portal:
 *       class: Ampa\PortalCliente\Testing\FakePortal
 */
final class FakePortal implements Portal
{
    private const string PREFIX = 'fake-portal:';

    /** @var array<string, list<Recipient>> */
    private static array $recipients = [];

    /**
     * @param list<string> $roles              los roles del portal en la aplicación ("usuario", "admin"…)
     * @param list<string> $notificationEmails vacío = el propio correo
     */
    public static function session(string $email, string $name, array $roles, array $notificationEmails = []): string
    {
        return self::PREFIX.base64_encode((string) json_encode([
            'email' => $email,
            'name' => $name,
            'roles' => $roles,
            'notificationEmails' => [] === $notificationEmails ? [$email] : $notificationEmails,
        ]));
    }

    /**
     * Quiénes saldrán en `recipients()` para ese rol. Se guarda en una
     * variable estática porque el cliente de pruebas de Symfony rehace el
     * contenedor entre peticiones: llama a reset() en el setUp().
     *
     * @param list<Recipient> $recipients
     */
    public static function recipientsFor(string $role, array $recipients): void
    {
        self::$recipients[$role] = $recipients;
    }

    public static function reset(): void
    {
        self::$recipients = [];
    }

    public function access(string $token): PortalAccess
    {
        if (!str_starts_with($token, self::PREFIX)) {
            throw new SessionRejected('Token que el portal de pruebas no reconoce.');
        }

        /** @var array{email: string, name: string, roles: list<string>, notificationEmails: list<string>} $data */
        $data = json_decode((string) base64_decode(substr($token, strlen(self::PREFIX)), true), true, 512, \JSON_THROW_ON_ERROR);

        return new PortalAccess($data['email'], $data['name'], $data['roles'], $data['notificationEmails']);
    }

    public function recipients(string $token, string $role): array
    {
        if ([] === $this->access($token)->getRoles()) {
            throw new SessionRejected('Sin rol en esta aplicación, el portal no da los correos.');
        }

        return self::$recipients[$role] ?? [];
    }
}
