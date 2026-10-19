<?php

declare(strict_types=1);

namespace App\Tests\Portal\Api;

use App\Portal\Application\User\GrantAccess;
use App\Portal\Application\User\GrantsChangeNotifier;
use App\Portal\Domain\User\EmailAddress;
use App\Portal\Domain\User\Grants;
use App\Tests\Double\RecordingGrantsChangeNotifier;
use App\Tests\Support\ApiTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Los tres casos de uso que cambian permisos (alta, ficha y `app:permisos:dar`)
 * avisan a Documentos, con el suite.yaml de verdad.
 */
final class GrantsChangeNoticesApiTest extends ApiTestCase
{
    private const string ADMIN = 'admin@example.com';

    protected function setUp(): void
    {
        parent::setUp();

        // Un solo kernel: el doble que se mira es el mismo que usa la petición.
        $this->client->disableReboot();
        $this->given(self::ADMIN, ['portal' => ['admin']]);
        $this->signedInAs(self::ADMIN);
    }

    #[Test]
    public function dar_de_alta_con_un_rol_de_documentos_avisa_a_documentos(): void
    {
        $this->request('POST', '/api/admin/users', ['email' => 'nueva@example.com', 'name' => 'Nueva', 'grants' => ['documentos' => ['miembro']]]);

        self::assertSame(201, $this->responseStatus());
        self::assertSame(['documentos'], $this->notified());
    }

    #[Test]
    public function dar_de_alta_sin_roles_de_documentos_no_avisa(): void
    {
        $this->request('POST', '/api/admin/users', ['email' => 'nueva@example.com', 'name' => 'Nueva', 'grants' => ['listados' => ['usuario']]]);

        self::assertSame(201, $this->responseStatus());
        self::assertSame([], $this->notified());
    }

    #[Test]
    public function guardar_la_ficha_avisa_solo_si_cambian_los_roles_de_documentos(): void
    {
        $user = $this->given('zoe@example.com', ['documentos' => ['miembro']]);
        $path = '/api/admin/users/'.$user->getId()->toString();

        // Solo el nombre: nada.
        $this->request('PUT', $path, ['name' => 'Zoe Nueva', 'active' => true, 'grants' => ['documentos' => ['miembro']]]);
        self::assertSame(200, $this->responseStatus());
        self::assertSame([], $this->notified());

        // Quitarle el rol: aviso.
        $this->request('PUT', $path, ['name' => 'Zoe Nueva', 'active' => true, 'grants' => ['listados' => ['usuario']]]);
        self::assertSame(200, $this->responseStatus());
        self::assertSame(['documentos'], $this->notified());
    }

    #[Test]
    public function desactivar_a_alguien_con_rol_en_documentos_avisa(): void
    {
        $user = $this->given('zoe@example.com', ['documentos' => ['miembro']]);

        $this->request('PUT', '/api/admin/users/'.$user->getId()->toString(), ['name' => 'Zoe', 'active' => false, 'grants' => ['documentos' => ['miembro']]]);

        self::assertSame(200, $this->responseStatus());
        self::assertSame(['documentos'], $this->notified());
    }

    #[Test]
    public function dar_acceso_avisa_si_añade_un_rol_y_no_si_ya_lo_tenia(): void
    {
        $this->given('zoe@example.com', ['documentos' => ['miembro']]);
        $grantAccess = self::getContainer()->get(GrantAccess::class);
        \assert($grantAccess instanceof GrantAccess);
        $zoe = EmailAddress::fromString('zoe@example.com');

        $grantAccess($zoe, null, Grants::fromArray(['documentos' => ['miembro']]));
        self::assertSame([], $this->notified());

        $grantAccess($zoe, null, Grants::fromArray(['documentos' => ['junta']]));
        self::assertSame(['documentos'], $this->notified());
    }

    /** @return list<string> */
    private function notified(): array
    {
        $notifier = self::getContainer()->get(GrantsChangeNotifier::class);
        \assert($notifier instanceof RecordingGrantsChangeNotifier);

        return $notifier->getNotified();
    }
}
