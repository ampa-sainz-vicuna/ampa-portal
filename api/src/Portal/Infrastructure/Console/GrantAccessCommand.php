<?php

declare(strict_types=1);

namespace App\Portal\Infrastructure\Console;

use App\Portal\Application\User\GrantAccess;
use App\Portal\Domain\User\EmailAddress;
use App\Portal\Domain\User\Grants;
use App\Portal\Domain\User\NotificationTarget;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Da permisos desde la consola, dando de alta a la persona si no existe.
 *
 *   bin/console app:permisos:dar admin@ampasainzvicuna.com portal:admin listados:usuario --nombre="Nombre Apellido"
 *   bin/console app:permisos:dar tesoreria@ampasainzvicuna.com fichajes:admin --segundo-correo=alguien@gmail.com
 *
 * Para lo que la pantalla no puede: el PRIMER administrador (sin él nadie
 * entra en la pantalla de permisos) y pasar al portal los accesos que ya había
 * en fichajes y listados. Solo suma permisos, nunca quita.
 */
#[AsCommand(name: 'app:permisos:dar', description: 'Da permisos a una persona (y la da de alta si no existe).')]
final readonly class GrantAccessCommand
{
    public function __construct(private GrantAccess $grantAccess)
    {
    }

    /**
     * @param list<string> $permisos
     */
    public function __invoke(
        SymfonyStyle $io,
        #[Argument('Correo de la cuenta de Google.')] string $correo,
        #[Argument('Uno o varios, como aplicacion:rol (p. ej. listados:usuario).')] array $permisos,
        #[Option('Nombre; obligatorio si la persona es nueva.')] ?string $nombre = null,
        #[Option('Segundo correo, donde también (o solo) recibir los avisos.')] ?string $segundoCorreo = null,
        #[Option('A dónde van los avisos: primary, secondary o both. Con segundo correo, both si no se dice.')] ?string $avisos = null,
    ): int {
        $roles = [];
        foreach ($permisos as $permiso) {
            [$application, $role] = array_pad(explode(':', $permiso, 2), 2, '');
            if ('' === $application || '' === $role) {
                $io->error(sprintf('"%s" no tiene la forma aplicacion:rol.', $permiso));

                return Command::INVALID;
            }

            $roles[$application][] = $role;
        }

        $notify = null === $avisos ? null : NotificationTarget::tryFrom($avisos);
        if (null !== $avisos && null === $notify) {
            $io->error('--avisos tiene que ser primary, secondary o both.');

            return Command::INVALID;
        }

        try {
            $user = ($this->grantAccess)(
                EmailAddress::fromString($correo),
                $nombre,
                Grants::fromArray($roles),
                null === $segundoCorreo ? null : EmailAddress::fromString($segundoCorreo),
                $notify,
            );
        } catch (\InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf('%s <%s>', $user->getName(), $user->getEmail()->getValue()));
        foreach ($user->getGrants()->toArray() as $application => $list) {
            $io->writeln(sprintf('  %s: %s', $application, implode(', ', $list)));
        }
        $io->writeln(sprintf('  avisos a: %s', implode(', ', array_map(
            static fn (EmailAddress $email): string => $email->getValue(),
            $user->getNotificationEmails(),
        ))));

        return Command::SUCCESS;
    }
}
