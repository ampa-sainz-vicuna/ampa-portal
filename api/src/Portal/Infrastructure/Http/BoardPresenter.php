<?php

declare(strict_types=1);

namespace App\Portal\Infrastructure\Http;

use App\Portal\Domain\Board\BoardMember;

/**
 * Un cargo en la pantalla *Junta* (solo administradores): la ficha entera,
 * con los datos personales. Lo que ven las demás aplicaciones es
 * PublicBoardPresenter, que no lleva DNI, dirección ni teléfono.
 */
final readonly class BoardPresenter
{
    /**
     * @return array{
     *     id: string,
     *     firstName: string,
     *     lastName: string,
     *     document: string,
     *     address: string,
     *     email: string,
     *     phone: string,
     *     position: string,
     *     startDate: string,
     *     endDate: string|null,
     *     userId: string|null
     * }
     */
    public function __invoke(BoardMember $member): array
    {
        return [
            'id' => $member->getId()->toString(),
            'firstName' => $member->getFirstName(),
            'lastName' => $member->getLastName(),
            'document' => $member->getDocument()->getValue(),
            'address' => $member->getAddress(),
            'email' => $member->getEmail()->getValue(),
            'phone' => $member->getPhone()->getValue(),
            'position' => $member->getPosition()->value,
            'startDate' => $member->getStartDate()->format('Y-m-d'),
            'endDate' => $member->getEndDate()?->format('Y-m-d'),
            'userId' => $member->getUserId()?->toString(),
        ];
    }
}
