<?php

declare(strict_types=1);

namespace App\Portal\Application\Board;

use App\Portal\Domain\Board\BoardPosition;
use App\Portal\Domain\Board\IdentityDocument;
use App\Portal\Domain\Board\PhoneNumber;
use App\Portal\Domain\User\EmailAddress;

/**
 * Los datos de la ficha de un cargo, ya como value objects: lo que reciben el
 * alta y la edición. Los textos (nombre, apellidos, dirección) los valida la
 * propia entidad.
 */
final readonly class BoardMemberData
{
    public function __construct(
        private string $firstName,
        private string $lastName,
        private IdentityDocument $document,
        private string $address,
        private EmailAddress $email,
        private PhoneNumber $phone,
        private BoardPosition $position,
        private \DateTimeImmutable $startDate,
    ) {
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function getDocument(): IdentityDocument
    {
        return $this->document;
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    public function getEmail(): EmailAddress
    {
        return $this->email;
    }

    public function getPhone(): PhoneNumber
    {
        return $this->phone;
    }

    public function getPosition(): BoardPosition
    {
        return $this->position;
    }

    public function getStartDate(): \DateTimeImmutable
    {
        return $this->startDate;
    }
}
