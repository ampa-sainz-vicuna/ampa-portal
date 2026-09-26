<?php

declare(strict_types=1);

namespace App\Portal\Application\User;

use App\Portal\Domain\User\User;
use App\Portal\Domain\User\UserRepository;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

/**
 * Apunta que alguien ha pasado por la suite (User::recordVisit), para la
 * "última entrada" de la pantalla de permisos.
 *
 * Nunca puede impedir entrar: si la base no deja guardar, se anota en el
 * registro y la petición sigue. Una fecha de última entrada no vale una
 * aplicación que no abre.
 */
final readonly class RecordVisit
{
    public function __construct(
        private UserRepository $users,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(User $user): void
    {
        if (!$user->recordVisit($this->clock->now())) {
            return;
        }

        try {
            $this->users->save($user);
        } catch (\Throwable $e) {
            $this->logger->warning('No se ha podido guardar la última entrada de {email}: {error}', [
                'email' => $user->getEmail()->getValue(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
