<?php

declare(strict_types=1);

namespace App\Tests\Portal\Api;

use App\Portal\Domain\User\NotificationTarget;
use App\Tests\Double\FakeServiceAccountVerifier;
use App\Tests\Support\ApiTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Lo que preguntan las aplicaciones al portal desde su servidor: el contrato
 * con el cliente (cliente/). Si uno de estos tests cambia, cambia el
 * contrato, y sube la segunda cifra de la versión.
 */
final class AccessApiTest extends ApiTestCase
{
    #[Test]
    public function devuelve_quien_es_sus_roles_en_esa_aplicacion_y_a_donde_avisarle(): void
    {
        $this->given('tesoreria@example.com', ['fichajes' => ['admin'], 'listados' => ['usuario']], secondaryEmail: 'alguien@example.org', notify: NotificationTarget::Secondary);

        $this->access('fichajes', $this->tokenFor('tesoreria@example.com'));

        self::assertSame(200, $this->responseStatus());
        self::assertSame([
            'email' => 'tesoreria@example.com',
            'name' => 'Tesoreria',
            'roles' => ['admin'],
            'notificationEmails' => ['alguien@example.org'],
            'applications' => $this->payload()['applications'],
        ], $this->payload());
    }

    #[Test]
    public function dice_a_que_aplicaciones_puede_ir_para_saltar_de_una_a_otra(): void
    {
        $this->given('tesoreria@example.com', ['fichajes' => ['admin'], 'listados' => ['usuario']]);

        $this->access('fichajes', $this->tokenFor('tesoreria@example.com'));

        $applications = $this->payload()['applications'];
        self::assertSame(['fichajes', 'listados'], array_column($applications, 'code'));
        self::assertSame(['code', 'name', 'url'], array_keys($applications[0]));
    }

    #[Test]
    public function sin_permiso_en_esa_aplicacion_contesta_con_roles_vacios(): void
    {
        // No es un error: el cliente decide (en fichajes, un contrato también
        // puede dar un rol).
        $this->given('admin@example.com', ['listados' => ['usuario']]);

        $this->access('fichajes', $this->tokenFor('admin@example.com'));

        self::assertSame(200, $this->responseStatus());
        self::assertSame([], $this->payload()['roles']);
    }

    #[Test]
    public function sin_token_es_un_401(): void
    {
        $this->request('GET', '/api/acceso?aplicacion=fichajes');

        self::assertSame(401, $this->responseStatus());
    }

    #[Test]
    public function con_un_token_que_no_vale_es_un_401(): void
    {
        $this->access('fichajes', 'basura');

        self::assertSame(401, $this->responseStatus());
    }

    #[Test]
    public function desactivada_es_un_401_aunque_el_token_siga_vigente(): void
    {
        $this->given('antiguo@example.com', ['fichajes' => ['admin']], active: false);

        $this->access('fichajes', $this->tokenFor('antiguo@example.com'));

        self::assertSame(401, $this->responseStatus());
    }

    #[Test]
    public function una_aplicacion_que_no_existe_es_un_404(): void
    {
        $this->given('admin@example.com', ['listados' => ['usuario']]);

        $this->access('inventada', $this->tokenFor('admin@example.com'));

        self::assertSame(404, $this->responseStatus());
    }

    #[Test]
    public function los_avisos_van_a_quien_tiene_el_rol_cada_uno_a_su_correo(): void
    {
        $this->given('empleada@example.com', ['fichajes' => ['empleado']]);
        $this->given('presidencia@example.com', ['fichajes' => ['admin']], secondaryEmail: 'presi@example.org', notify: NotificationTarget::Both);
        $this->given('tesoreria@example.com', ['fichajes' => ['admin']]);
        $this->given('baja@example.com', ['fichajes' => ['admin']], active: false);
        $this->given('listados@example.com', ['listados' => ['usuario']]);

        $this->request('GET', '/api/avisos?aplicacion=fichajes&rol=admin', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->tokenFor('empleada@example.com'),
        ]);

        self::assertSame(200, $this->responseStatus());
        self::assertSame([
            ['name' => 'Presidencia', 'emails' => ['presidencia@example.com', 'presi@example.org']],
            ['name' => 'Tesoreria', 'emails' => ['tesoreria@example.com']],
        ], $this->payload());
    }

    #[Test]
    public function los_correos_de_una_aplicacion_no_se_los_da_a_quien_no_entra_en_ella(): void
    {
        $this->given('tesoreria@example.com', ['fichajes' => ['admin']]);
        $this->given('listados@example.com', ['listados' => ['usuario']]);

        $this->request('GET', '/api/avisos?aplicacion=fichajes&rol=admin', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->tokenFor('listados@example.com'),
        ]);

        self::assertSame(403, $this->responseStatus());
    }

    #[Test]
    public function los_avisos_de_un_rol_que_no_existe_son_un_404(): void
    {
        $this->request('GET', '/api/avisos?aplicacion=fichajes&rol=jefe');

        self::assertSame(404, $this->responseStatus());
    }

    #[Test]
    public function las_personas_de_una_aplicacion_con_el_correo_de_su_cuenta(): void
    {
        // Alberto quiere los avisos en su correo personal, pero su identidad
        // sigue siendo la cuenta: es la que sale aquí.
        $this->given('alberto@example.com', ['tareas' => ['miembro']], secondaryEmail: 'alberto@example.org', notify: NotificationTarget::Secondary);
        $this->given('presidencia@example.com', ['tareas' => ['miembro', 'admin']]);
        $this->given('baja@example.com', ['tareas' => ['miembro']], active: false);
        $this->given('listados@example.com', ['listados' => ['usuario']]);

        $this->request('GET', '/api/personas?aplicacion=tareas', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->tokenFor('alberto@example.com'),
        ]);

        self::assertSame(200, $this->responseStatus());
        self::assertSame([
            ['name' => 'Alberto', 'email' => 'alberto@example.com', 'roles' => ['miembro'], 'notificationEmails' => ['alberto@example.org']],
            ['name' => 'Presidencia', 'email' => 'presidencia@example.com', 'roles' => ['admin', 'miembro'], 'notificationEmails' => ['presidencia@example.com']],
        ], $this->payload());
    }

    #[Test]
    public function las_personas_de_una_aplicacion_no_se_las_da_a_quien_no_entra_en_ella(): void
    {
        $this->given('alberto@example.com', ['tareas' => ['miembro']]);
        $this->given('listados@example.com', ['listados' => ['usuario']]);

        $this->request('GET', '/api/personas?aplicacion=tareas', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->tokenFor('listados@example.com'),
        ]);
        self::assertSame(403, $this->responseStatus());

        $this->request('GET', '/api/personas?aplicacion=tareas');
        self::assertSame(401, $this->responseStatus());

        $this->request('GET', '/api/personas?aplicacion=quiniela');
        self::assertSame(404, $this->responseStatus());
    }

    #[Test]
    public function el_servidor_de_una_aplicacion_sin_nadie_detras_pregunta_con_su_cuenta_de_servicio(): void
    {
        // El resumen diario de tareas, a las 5:00: no hay sesión de nadie.
        $this->given('alberto@example.com', ['tareas' => ['miembro']], secondaryEmail: 'alberto@example.org', notify: NotificationTarget::Secondary);
        $this->given('presidencia@example.com', ['tareas' => ['miembro', 'admin']]);
        $service = ['HTTP_AUTHORIZATION' => 'Bearer '.FakeServiceAccountVerifier::TOKEN];

        $this->request('GET', '/api/personas?aplicacion=tareas', server: $service);
        self::assertSame(200, $this->responseStatus());
        self::assertSame(['alberto@example.com', 'presidencia@example.com'], array_column($this->payload(), 'email'));

        $this->request('GET', '/api/avisos?aplicacion=tareas&rol=admin', server: $service);
        self::assertSame(200, $this->responseStatus());
        self::assertSame([['name' => 'Presidencia', 'emails' => ['presidencia@example.com']]], $this->payload());
    }

    #[Test]
    public function una_cuenta_de_servicio_no_es_una_persona_para_el_acceso(): void
    {
        $this->access('tareas', FakeServiceAccountVerifier::TOKEN);
        self::assertSame(401, $this->responseStatus());

        // Y un token que no es ni sesión ni de la suite, tampoco vale para lo demás.
        $this->request('GET', '/api/personas?aplicacion=tareas', server: ['HTTP_AUTHORIZATION' => 'Bearer cuenta-de-servicio:otra@example.com']);
        self::assertSame(401, $this->responseStatus());
    }

    private function access(string $application, string $token): void
    {
        $this->request('GET', '/api/acceso?aplicacion='.$application, server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);
    }
}
