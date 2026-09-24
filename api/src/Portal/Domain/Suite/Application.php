<?php

declare(strict_types=1);

namespace App\Portal\Domain\Suite;

/**
 * Una aplicación de la suite, tal y como la ve el portal: cómo se llama, dónde
 * está y qué roles se pueden dar en ella.
 *
 * Qué SIGNIFICA cada rol lo decide la aplicación, no el portal. Por ejemplo,
 * en fichajes "empleado" además exige tener un contrato en vigor, que es cosa
 * de fichajes. El portal solo guarda quién tiene qué.
 */
final readonly class Application
{
    /**
     * @param array<string, string> $roles código → nombre para la pantalla
     */
    public function __construct(
        private string $code,
        private string $name,
        private string $url,
        private array $roles,
    ) {
        if ([] === $roles) {
            throw new \InvalidArgumentException(sprintf('La aplicación "%s" no tiene ningún rol.', $code));
        }
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /** Dónde se entra. Vacía para el propio portal, que no sale en las tarjetas. */
    public function getUrl(): string
    {
        return $this->url;
    }

    /** @return array<string, string> código → nombre */
    public function getRoles(): array
    {
        return $this->roles;
    }

    public function hasRole(string $role): bool
    {
        return array_key_exists($role, $this->roles);
    }
}
