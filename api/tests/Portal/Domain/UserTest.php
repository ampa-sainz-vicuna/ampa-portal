<?php

declare(strict_types=1);

namespace App\Tests\Portal\Domain;

use App\Portal\Domain\User\EmailAddress;
use App\Portal\Domain\User\Grants;
use App\Portal\Domain\User\NotificationTarget;
use App\Portal\Domain\User\User;
use App\Portal\Domain\User\UserId;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    #[Test]
    public function el_correo_se_guarda_en_minusculas(): void
    {
        // Si no, quien se dio de alta como "Admin@…" no podría entrar: Google
        // devuelve el correo en minúsculas.
        self::assertSame('admin@ampasainzvicuna.com', EmailAddress::fromString(' Admin@AmpaSainzVicuna.com ')->getValue());
    }

    #[Test]
    public function sin_segundo_correo_los_avisos_van_al_principal(): void
    {
        self::assertSame(['tesoreria@ampasainzvicuna.com'], $this->emails($this->user()));
    }

    #[Test]
    public function con_segundo_correo_cada_uno_elige_a_donde_van_los_avisos(): void
    {
        $user = $this->user();
        $personal = EmailAddress::fromString('alguien@gmail.com');

        $user->changeContact($personal, NotificationTarget::Primary);
        self::assertSame(['tesoreria@ampasainzvicuna.com'], $this->emails($user));

        $user->changeContact($personal, NotificationTarget::Secondary);
        self::assertSame(['alguien@gmail.com'], $this->emails($user));

        $user->changeContact($personal, NotificationTarget::Both);
        self::assertSame(['tesoreria@ampasainzvicuna.com', 'alguien@gmail.com'], $this->emails($user));
    }

    #[Test]
    public function no_se_pueden_pedir_avisos_en_un_segundo_correo_que_no_hay(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->user()->changeContact(null, NotificationTarget::Secondary);
    }

    #[Test]
    public function un_segundo_correo_igual_al_principal_no_cuenta(): void
    {
        // Si contara, con "a los dos" cada aviso llegaría dos veces.
        $user = $this->user();
        $user->changeContact(EmailAddress::fromString('Tesoreria@ampasainzvicuna.com'), NotificationTarget::Primary);

        self::assertNull($user->getSecondaryEmail());
    }

    #[Test]
    public function sin_permisos_no_puede_entrar_aunque_este_activo(): void
    {
        $user = User::register(UserId::generate(), EmailAddress::fromString('a@b.com'), 'Alguien', Grants::none());

        self::assertFalse($user->canSignIn());
    }

    #[Test]
    public function desactivada_no_tiene_roles_en_ninguna_aplicacion(): void
    {
        $user = $this->user();
        $user->deactivate();

        self::assertFalse($user->canSignIn());
        self::assertSame([], $user->rolesIn('fichajes'));
        self::assertFalse($user->isSuiteAdmin());
        // Los permisos siguen ahí por si vuelve.
        self::assertSame(['admin'], $user->getGrants()->rolesIn('fichajes'));
    }

    #[Test]
    public function gestiona_los_permisos_quien_es_admin_del_portal(): void
    {
        $user = $this->user();
        self::assertFalse($user->isSuiteAdmin());

        $user->changeGrants($user->getGrants()->with('portal', 'admin'));
        self::assertTrue($user->isSuiteAdmin());
    }

    #[Test]
    public function el_nombre_no_puede_quedarse_vacio(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->user()->rename('   ');
    }

    private function user(): User
    {
        return User::register(
            UserId::generate(),
            EmailAddress::fromString('tesoreria@ampasainzvicuna.com'),
            'Tesorería',
            Grants::fromArray(['fichajes' => ['admin']]),
        );
    }

    /** @return list<string> */
    private function emails(User $user): array
    {
        return array_map(static fn (EmailAddress $email): string => $email->getValue(), $user->getNotificationEmails());
    }
}
