<?php

declare(strict_types=1);

namespace App\Portal\Domain\Board;

use App\Portal\Domain\User\EmailAddress;
use App\Portal\Domain\User\UserId;

/**
 * Una persona en un cargo de la junta durante un periodo. Raíz de agregado.
 *
 * Nunca se borra: al dejar el cargo se le pone fecha de fin y queda como
 * historia (quién fue la secretaria en 2024 se sigue pudiendo saber). Si la
 * misma persona vuelve, o cambia de cargo, es un registro nuevo.
 *
 * Datos personales (DNI, dirección, teléfono): solo los ve quien gestiona los
 * permisos de la suite; las demás aplicaciones solo reciben nombre, correo de
 * contacto y cargo. El correo de contacto NO es la cuenta con la que entra:
 * esa es la del usuario asociado, que es opcional.
 */
final class BoardMember
{
    private function __construct(
        private readonly BoardMemberId $id,
        private string $firstName,
        private string $lastName,
        private IdentityDocument $document,
        private string $address,
        private EmailAddress $email,
        private PhoneNumber $phone,
        private BoardPosition $position,
        private \DateTimeImmutable $startDate,
        private ?\DateTimeImmutable $endDate,
        private ?UserId $userId,
    ) {
    }

    public static function appoint(
        BoardMemberId $id,
        string $firstName,
        string $lastName,
        IdentityDocument $document,
        string $address,
        EmailAddress $email,
        PhoneNumber $phone,
        BoardPosition $position,
        \DateTimeImmutable $startDate,
    ): self {
        return new self(
            $id,
            self::text($firstName, 'el nombre', 120),
            self::text($lastName, 'los apellidos', 180),
            $document,
            self::text($address, 'la dirección', 300),
            $email,
            $phone,
            $position,
            $startDate->setTime(0, 0),
            null,
            null,
        );
    }

    /**
     * Sustituye los datos de la ficha. No toca la fecha de fin (para eso está
     * end()) ni el usuario asociado (linkUser()). El caso de uso ya ha
     * comprobado que el cargo está libre si cambia.
     *
     * @throws \InvalidArgumentException si algún dato no vale o la fecha de inicio queda después de la de fin
     */
    public function update(
        string $firstName,
        string $lastName,
        IdentityDocument $document,
        string $address,
        EmailAddress $email,
        PhoneNumber $phone,
        BoardPosition $position,
        \DateTimeImmutable $startDate,
    ): void {
        $startDate = $startDate->setTime(0, 0);

        if (null !== $this->endDate && $startDate > $this->endDate) {
            throw new \InvalidArgumentException('La fecha de inicio no puede ser posterior a la de fin.');
        }

        $this->firstName = self::text($firstName, 'el nombre', 120);
        $this->lastName = self::text($lastName, 'los apellidos', 180);
        $this->document = $document;
        $this->address = self::text($address, 'la dirección', 300);
        $this->email = $email;
        $this->phone = $phone;
        $this->position = $position;
        $this->startDate = $startDate;
    }

    /**
     * Deja el cargo: pone la fecha de fin. La ficha se queda como historia.
     *
     * @throws BoardError                si ya estaba dada de baja
     * @throws \InvalidArgumentException si la fecha es anterior al inicio
     */
    public function end(\DateTimeImmutable $endDate): void
    {
        if (null !== $this->endDate) {
            throw BoardError::alreadyEnded();
        }

        $endDate = $endDate->setTime(0, 0);

        if ($endDate < $this->startDate) {
            throw new \InvalidArgumentException('La fecha de fin no puede ser anterior a la de inicio.');
        }

        $this->endDate = $endDate;
    }

    /** null: sin usuario asociado. */
    public function linkUser(?UserId $userId): void
    {
        $this->userId = $userId;
    }

    public function isActive(): bool
    {
        return null === $this->endDate;
    }

    public function getId(): BoardMemberId
    {
        return $this->id;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function getFullName(): string
    {
        return $this->firstName.' '.$this->lastName;
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

    public function getEndDate(): ?\DateTimeImmutable
    {
        return $this->endDate;
    }

    public function getUserId(): ?UserId
    {
        return $this->userId;
    }

    /** Los mensajes dicen qué campo falla, nunca su contenido (dirección, nombre…). */
    private static function text(string $value, string $label, int $maxLength): string
    {
        $value = trim($value);

        if ('' === $value) {
            throw new \InvalidArgumentException(sprintf('Falta %s.', $label));
        }

        if (mb_strlen($value) > $maxLength) {
            throw new \InvalidArgumentException(sprintf('Es demasiado largo (máximo %2$d caracteres): %1$s.', $label, $maxLength));
        }

        return $value;
    }
}
