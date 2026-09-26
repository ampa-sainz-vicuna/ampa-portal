<?php

declare(strict_types=1);

namespace App\Tests\Portal\Api;

use App\Portal\Domain\User\NotificationTarget;
use App\Tests\Support\ApiTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * La pantalla de permisos de la suite y "mis correos".
 */
final class AdminUsersApiTest extends ApiTestCase
{
    private const string ADMIN = 'admin@ampasainzvicuna.com';

    protected function setUp(): void
    {
        parent::setUp();

        $this->given(self::ADMIN, ['portal' => ['admin'], 'listados' => ['usuario']]);
        $this->signedInAs(self::ADMIN);
    }

    #[Test]
    public function lista_a_todos_por_nombre_con_sus_permisos_y_correos(): void
    {
        $this->given('zoe@ampasainzvicuna.com', ['listados' => ['usuario']]);
        $this->given('alvaro@ampasainzvicuna.com', ['fichajes' => ['admin']], secondaryEmail: 'alvaro@gmail.com', notify: NotificationTarget::Both);

        $this->request('GET', '/api/admin/users');

        self::assertSame(200, $this->responseStatus());
        $users = $this->payload();
        self::assertSame(['Admin', 'Alvaro', 'Zoe'], array_column($users, 'name'));
        self::assertSame(['fichajes' => ['admin']], $users[1]['grants']);
        self::assertSame('alvaro@gmail.com', $users[1]['secondaryEmail']);
        self::assertSame('both', $users[1]['notify']);
    }

    #[Test]
    public function dice_cuando_entro_cada_uno_por_ultima_vez_y_quien_no_ha_entrado_nunca(): void
    {
        $zoe = $this->given('zoe@ampasainzvicuna.com', ['listados' => ['usuario']]);
        $this->given('alvaro@ampasainzvicuna.com', ['listados' => ['usuario']]);

        // Zoe abre listados: su servidor pregunta al portal con su sesión.
        $this->request('GET', '/api/acceso?aplicacion=listados', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->tokenFor('zoe@ampasainzvicuna.com')]);
        self::assertSame(200, $this->responseStatus());
        self::assertNotNull($this->reload($zoe)->getLastSeenAt());

        $this->request('GET', '/api/admin/users');

        $users = array_column($this->payload(), null, 'email');
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', (string) $users['zoe@ampasainzvicuna.com']['lastSeenAt']);
        self::assertNull($users['alvaro@ampasainzvicuna.com']['lastSeenAt']);
    }

    #[Test]
    public function da_de_alta_a_alguien_con_sus_permisos(): void
    {
        $this->request('POST', '/api/admin/users', [
            'email' => 'Secretaria@AmpaSainzVicuna.com',
            'name' => 'Secretaría',
            'grants' => ['fichajes' => ['admin'], 'listados' => ['usuario']],
            'secondaryEmail' => 'secre@gmail.com',
            'notify' => 'secondary',
        ]);

        self::assertSame(201, $this->responseStatus());
        $user = $this->payload();
        self::assertSame('secretaria@ampasainzvicuna.com', $user['email']);
        self::assertSame(['fichajes' => ['admin'], 'listados' => ['usuario']], $user['grants']);
        self::assertTrue($user['active']);
        self::assertSame('secondary', $user['notify']);
    }

    #[Test]
    public function el_segundo_correo_es_opcional_al_dar_de_alta(): void
    {
        $this->request('POST', '/api/admin/users', ['email' => 'a@ampasainzvicuna.com', 'name' => 'A', 'grants' => ['listados' => ['usuario']]]);

        self::assertSame(201, $this->responseStatus());
        self::assertNull($this->payload()['secondaryEmail']);
        self::assertSame('primary', $this->payload()['notify']);
    }

    #[Test]
    public function el_mismo_correo_dos_veces_es_un_409(): void
    {
        $this->request('POST', '/api/admin/users', ['email' => self::ADMIN, 'name' => 'Otra vez', 'grants' => []]);

        self::assertSame(409, $this->responseStatus());
    }

    #[Test]
    public function un_rol_que_la_aplicacion_no_tiene_es_un_422(): void
    {
        $this->request('POST', '/api/admin/users', ['email' => 'a@ampasainzvicuna.com', 'name' => 'A', 'grants' => ['listados' => ['admin']]]);

        self::assertSame(422, $this->responseStatus());
        self::assertStringContainsString('no tiene el rol "admin"', $this->payload()['error']);
    }

    #[Test]
    public function avisos_al_segundo_correo_sin_segundo_correo_es_un_422(): void
    {
        $this->request('POST', '/api/admin/users', ['email' => 'a@ampasainzvicuna.com', 'name' => 'A', 'grants' => [], 'notify' => 'secondary']);

        self::assertSame(422, $this->responseStatus());
    }

    #[Test]
    public function sin_los_campos_obligatorios_es_un_400(): void
    {
        $this->request('POST', '/api/admin/users', ['email' => 'a@ampasainzvicuna.com']);

        self::assertSame(400, $this->responseStatus());
    }

    #[Test]
    public function guardar_la_ficha_sustituye_nombre_estado_permisos_y_correos(): void
    {
        $user = $this->given('tesoreria@ampasainzvicuna.com', ['fichajes' => ['admin'], 'listados' => ['usuario']]);

        $this->request('PUT', '/api/admin/users/'.$user->getId()->toString(), [
            'name' => 'Tesorería',
            'active' => false,
            'grants' => ['fichajes' => ['admin']],
            'secondaryEmail' => 'teso@gmail.com',
            'notify' => 'both',
        ]);

        self::assertSame(200, $this->responseStatus());
        $saved = $this->reload($user);
        self::assertSame('Tesorería', $saved->getName());
        self::assertFalse($saved->isActive());
        self::assertSame(['fichajes' => ['admin']], $saved->getGrants()->toArray());
        self::assertSame('teso@gmail.com', $saved->getSecondaryEmail()?->getValue());
        self::assertSame(NotificationTarget::Both, $saved->getNotify());
    }

    #[Test]
    public function nadie_puede_quitarse_a_si_mismo_la_gestion_de_permisos(): void
    {
        $me = $this->given('yo@ampasainzvicuna.com', ['portal' => ['admin']]);
        $this->signedInAs('yo@ampasainzvicuna.com');

        $this->request('PUT', '/api/admin/users/'.$me->getId()->toString(), ['name' => 'Yo', 'active' => true, 'grants' => ['listados' => ['usuario']]]);

        self::assertSame(409, $this->responseStatus());
    }

    #[Test]
    public function ni_desactivarse(): void
    {
        $me = $this->given('yo@ampasainzvicuna.com', ['portal' => ['admin']]);
        $this->signedInAs('yo@ampasainzvicuna.com');

        $this->request('PUT', '/api/admin/users/'.$me->getId()->toString(), ['name' => 'Yo', 'active' => false, 'grants' => ['portal' => ['admin']]]);

        self::assertSame(409, $this->responseStatus());
    }

    #[Test]
    public function guardar_a_alguien_que_no_existe_es_un_404(): void
    {
        $this->request('PUT', '/api/admin/users/0192a4b0-0000-7000-8000-000000000000', ['name' => 'X', 'active' => true, 'grants' => []]);

        self::assertSame(404, $this->responseStatus());
    }

    #[Test]
    public function quien_no_gestiona_permisos_no_ve_la_pantalla(): void
    {
        $this->given('usuario@ampasainzvicuna.com', ['listados' => ['usuario']]);
        $this->signedInAs('usuario@ampasainzvicuna.com');

        $this->request('GET', '/api/admin/users');

        self::assertSame(403, $this->responseStatus());
        self::assertSame('No tienes permiso para esto.', $this->payload()['error']);
    }

    #[Test]
    public function la_pantalla_recibe_las_aplicaciones_y_sus_roles(): void
    {
        $this->request('GET', '/api/admin/applications');

        self::assertSame(200, $this->responseStatus());
        $fichajes = $this->payload()[0];
        self::assertSame('fichajes', $fichajes['code']);
        self::assertSame([['code' => 'empleado', 'name' => 'Empleado'], ['code' => 'admin', 'name' => 'Administración']], $fichajes['roles']);
    }

    #[Test]
    public function cada_uno_elige_su_segundo_correo_y_a_donde_van_sus_avisos(): void
    {
        $this->given('vocal@ampasainzvicuna.com', ['listados' => ['usuario']]);
        $this->signedInAs('vocal@ampasainzvicuna.com');

        $this->request('PUT', '/api/me/contacto', ['secondaryEmail' => 'vocal@gmail.com', 'notify' => 'secondary']);

        self::assertSame(200, $this->responseStatus());
        self::assertSame('vocal@gmail.com', $this->payload()['secondaryEmail']);
        self::assertSame('secondary', $this->payload()['notify']);

        // Y con eso no puede tocarse nada más de su ficha: los permisos siguen igual.
        $this->request('GET', '/api/me');
        self::assertSame(['listados'], array_column($this->payload()['applications'], 'code'));
    }

    #[Test]
    public function vaciar_el_segundo_correo_vuelve_a_mandar_los_avisos_al_principal(): void
    {
        $this->given('vocal@ampasainzvicuna.com', ['listados' => ['usuario']], secondaryEmail: 'vocal@gmail.com', notify: NotificationTarget::Secondary);
        $this->signedInAs('vocal@ampasainzvicuna.com');

        $this->request('PUT', '/api/me/contacto', ['secondaryEmail' => '', 'notify' => 'primary']);

        self::assertSame(200, $this->responseStatus());
        self::assertNull($this->payload()['secondaryEmail']);
    }

    #[Test]
    public function un_segundo_correo_que_no_es_un_correo_es_un_422(): void
    {
        $this->request('PUT', '/api/me/contacto', ['secondaryEmail' => 'esto no', 'notify' => 'both']);

        self::assertSame(422, $this->responseStatus());
    }
}
