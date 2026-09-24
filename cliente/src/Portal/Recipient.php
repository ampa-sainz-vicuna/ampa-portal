<?php

declare(strict_types=1);

namespace Ampa\PortalCliente\Portal;

/**
 * Alguien a quien avisar, con los correos que haya elegido (el de la cuenta,
 * el segundo o los dos).
 */
final readonly class Recipient
{
    /**
     * @param list<string> $emails
     */
    public function __construct(
        private string $name,
        private array $emails,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    /** @return list<string> */
    public function getEmails(): array
    {
        return $this->emails;
    }
}
