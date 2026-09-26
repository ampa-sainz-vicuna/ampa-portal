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
        $this->given('tesoreria@ampasainzvicuna.com', ['fichajes' => ['admin'], 'listados' => ['usuario']], secondaryEmail: 'alguien@gmail.com', notify: NotificationTarget::Secondary);

        $this->access('fichajes', $this->tokenFor('tesoreria@ampasainzvicuna.com'));

        self::assertSame(200, $this->responseStatus());
        self::assertSame([
            'email' => 'tesoreria@ampasainzvicuna.com',
            'name' => 'Tesoreria',
            'roles' => ['admin'],
            'notificationEmails' => ['alguien@gmail.com'],
            'applications' => $this->payload()['applications'],
        ], $this->payload());
    }

    #[Test]
    public function dice_a_que_aplicaciones_puede_ir_para_saltar_de_una_a_otra(): void
    {
        $this->given('tesoreria@ampasainzvicuna.com', ['fichajes' => ['admin'], 'listados' => ['usuario']]);

        $this->access('fichajes', $this->tokenFor('tesoreria@ampasainzvicuna.com'));

        $applications = $this->payload()['applications'];
        self::assertSame(['fichajes', 'listados'], array_column($applications, 'code'));
        self::assertSame(['code', 'name', 'url'], array_keys($applications[0]));
    }

    #[Test]
    public function sin_permiso_en_esa_aplicacion_contesta_con_roles_vacios(): void
    {
        // No es un error: el cliente decide (en fichajes, un contrato también
        // puede dar un rol).
        $this->given('admin@ampasainzvicuna.com', ['listados' => ['usuario']]);

        $this->access('fichajes', $this->tokenFor('admin@ampasainzvicuna.com'));

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
        $this->given('antiguo@ampasainzvicuna.com', ['fichajes' => ['admin']], active: false);

        $this->access('fichajes', $this->tokenFor('antiguo@ampasainzvicuna.com'));

        self::assertSame(401, $this->responseStatus());
    }

    #[Test]
    public function una_aplicacion_que_no_existe_es_un_404(): void
    {
        $this->given('admin@ampasainzvicuna.com', ['listados' => ['usuario']]);

        $this->access('inventada', $this->tokenFor('admin@ampasainzvicuna.com'));

        self::assertSame(404, $this->responseStatus());
    }

    #[Test]
    public function los_avisos_van_a_quien_tiene_el_rol_cada_uno_a_su_correo(): void
    {
        $this->given('empleada@ampasainzvicuna.com', ['fichajes' => ['empleado']]);
        $this->given('presidencia@ampasainzvicuna.com', ['fichajes' => ['admin']], secondaryEmail: 'presi@gmail.com', notify: NotificationTarget::Both);
        $this->given('tesoreria@ampasainzvicuna.com', ['fichajes' => ['admin']]);
        $this->given('baja@ampasainzvicuna.com', ['fichajes' => ['admin']], active: false);
        $this->given('listados@ampasainzvicuna.com', ['listados' => ['usuario']]);

        $this->request('GET', '/api/avisos?aplicacion=fichajes&rol=admin', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->tokenFor('empleada@ampasainzvicuna.com'),
        ]);

        self::assertSame(200, $this->responseStatus());
        self::assertSame([
            ['name' => 'Presidencia', 'emails' => ['presidencia@ampasainzvicuna.com', 'presi@gmail.com']],
            ['name' => 'Tesoreria', 'emails' => ['tesoreria@ampasainzvicuna.com']],
        ], $this->payload());
    }

    #[Test]
    public function los_correos_de_una_aplicacion_no_se_los_da_a_quien_no_entra_en_ella(): void
    {
        $this->given('tesoreria@ampasainzvicuna.com', ['fichajes' => ['admin']]);
        $this->given('listados@ampasainzvicuna.com', ['listados' => ['usuario']]);

        $this->request('GET', '/api/avisos?aplicacion=fichajes&rol=admin', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->tokenFor('listados@ampasainzvicuna.com'),
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
        $this->given('alberto@ampasainzvicuna.com', ['tareas' => ['miembro']], secondaryEmail: 'alberto@gmail.com', notify: NotificationTarget::Secondary);
        $this->given('presidencia@ampasainzvicuna.com', ['tareas' => ['miembro', 'admin']]);
        $this->given('baja@ampasainzvicuna.com', ['tareas' => ['miembro']], active: false);
        $this->given('listados@ampasainzvicuna.com', ['listados' => ['usuario']]);

        $this->request('GET', '/api/personas?aplicacion=tareas', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->tokenFor('alberto@ampasainzvicuna.com'),
        ]);

        self::assertSame(200, $this->responseStatus());
        self::assertSame([
            ['name' => 'Alberto', 'email' => 'alberto@ampasainzvicuna.com', 'roles' => ['miembro'], 'notificationEmails' => ['alberto@gmail.com']],
            ['name' => 'Presidencia', 'email' => 'presidencia@ampasainzvicuna.com', 'roles' => ['admin', 'miembro'], 'notificationEmails' => ['presidencia@ampasainzvicuna.com']],
        ], $this->payload());
    }

    #[Test]
    public function las_personas_de_una_aplicacion_no_se_las_da_a_quien_no_entra_en_ella(): void
    {
        $this->given('alberto@ampasainzvicuna.com', ['tareas' => ['miembro']]);
        $this->given('listados@ampasainzvicuna.com', ['listados' => ['usuario']]);

        $this->request('GET', '/api/personas?aplicacion=tareas', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->tokenFor('listados@ampasainzvicuna.com'),
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
        $this->given('alberto@ampasainzvicuna.com', ['tareas' => ['miembro']], secondaryEmail: 'alberto@gmail.com', notify: NotificationTarget::Secondary);
        $this->given('presidencia@ampasainzvicuna.com', ['tareas' => ['miembro', 'admin']]);
        $service = ['HTTP_AUTHORIZATION' => 'Bearer '.FakeServiceAccountVerifier::TOKEN];

        $this->request('GET', '/api/personas?aplicacion=tareas', server: $service);
        self::assertSame(200, $this->responseStatus());
        self::assertSame(['alberto@ampasainzvicuna.com', 'presidencia@ampasainzvicuna.com'], array_column($this->payload(), 'email'));

        $this->request('GET', '/api/avisos?aplicacion=tareas&rol=admin', server: $service);
        self::assertSame(200, $this->responseStatus());
        self::assertSame([['name' => 'Presidencia', 'emails' => ['presidencia@ampasainzvicuna.com']]], $this->payload());
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
