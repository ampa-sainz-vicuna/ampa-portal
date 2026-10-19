<?php

declare(strict_types=1);

namespace App\Tests\Portal\Domain;

use App\Portal\Application\User\GrantsChangeAnnouncer;
use App\Portal\Domain\Suite\Application;
use App\Portal\Domain\Suite\ApplicationCatalog;
use App\Portal\Domain\User\EmailAddress;
use App\Portal\Domain\User\Grants;
use App\Portal\Domain\User\User;
use App\Portal\Domain\User\UserId;
use App\Tests\Double\RecordingGrantsChangeNotifier;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GrantsChangeAnnouncerTest extends TestCase
{
    private RecordingGrantsChangeNotifier $notifier;
    private GrantsChangeAnnouncer $announcer;

    protected function setUp(): void
    {
        $this->notifier = new RecordingGrantsChangeNotifier();
        $this->announcer = new GrantsChangeAnnouncer(
            new ApplicationCatalog([
                new Application('portal', 'Portal', '', ['admin' => 'Admin']),
                new Application('documentos', 'Documentos', 'https://documentos.ejemplo', ['miembro' => 'Miembro', 'junta' => 'Junta'], grantNotices: true),
                new Application('listados', 'Listados', 'https://listados.ejemplo', ['usuario' => 'Usuario']),
            ]),
            $this->notifier,
        );
    }

    #[Test]
    public function avisa_a_documentos_al_darle_un_rol_a_alguien(): void
    {
        $user = $this->user(['listados' => ['usuario']]);
        $before = $this->announcer->snapshot($user);

        $user->changeGrants(Grants::fromArray(['listados' => ['usuario'], 'documentos' => ['miembro']]));
        $this->announcer->announce($before, $user);

        self::assertSame(['documentos'], $this->notifier->getNotified());
    }

    #[Test]
    public function avisa_al_quitarle_un_rol(): void
    {
        $user = $this->user(['documentos' => ['miembro', 'junta']]);
        $before = $this->announcer->snapshot($user);

        $user->changeGrants(Grants::fromArray(['documentos' => ['miembro']]));
        $this->announcer->announce($before, $user);

        self::assertSame(['documentos'], $this->notifier->getNotified());
    }

    #[Test]
    public function avisa_al_desactivar_a_la_persona_porque_deja_de_tener_roles(): void
    {
        $user = $this->user(['documentos' => ['miembro']]);
        $before = $this->announcer->snapshot($user);

        $user->deactivate();
        $this->announcer->announce($before, $user);

        self::assertSame(['documentos'], $this->notifier->getNotified());
    }

    #[Test]
    public function no_avisa_si_solo_cambian_los_roles_de_una_aplicacion_sin_avisos(): void
    {
        $user = $this->user(['documentos' => ['miembro']]);
        $before = $this->announcer->snapshot($user);

        $user->changeGrants(Grants::fromArray(['documentos' => ['miembro'], 'listados' => ['usuario']]));
        $this->announcer->announce($before, $user);

        self::assertSame([], $this->notifier->getNotified());
    }

    #[Test]
    public function no_avisa_si_solo_cambia_el_nombre_o_el_orden_de_los_roles(): void
    {
        $user = $this->user(['documentos' => ['miembro', 'junta']]);
        $before = $this->announcer->snapshot($user);

        $user->rename('Otro nombre');
        $user->changeGrants(Grants::fromArray(['documentos' => ['junta', 'miembro']]));
        $this->announcer->announce($before, $user);

        self::assertSame([], $this->notifier->getNotified());
    }

    #[Test]
    public function una_persona_nueva_con_un_rol_de_documentos_avisa_y_sin_el_no(): void
    {
        $this->announcer->announce([], $this->user(['listados' => ['usuario']]));
        self::assertSame([], $this->notifier->getNotified());

        $this->announcer->announce([], $this->user(['documentos' => ['miembro']]));
        self::assertSame(['documentos'], $this->notifier->getNotified());
    }

    #[Test]
    public function una_aplicacion_sin_direccion_no_recibe_avisos_aunque_los_pida(): void
    {
        $announcer = new GrantsChangeAnnouncer(
            new ApplicationCatalog([
                new Application('portal', 'Portal', '', ['admin' => 'Admin'], grantNotices: true),
            ]),
            $this->notifier,
        );

        $announcer->announce([], $this->user(['portal' => ['admin']]));

        self::assertSame([], $this->notifier->getNotified());
    }

    /**
     * @param array<string, list<string>> $grants
     */
    private function user(array $grants): User
    {
        return User::register(UserId::generate(), EmailAddress::fromString('persona@example.com'), 'Persona', Grants::fromArray($grants));
    }
}
