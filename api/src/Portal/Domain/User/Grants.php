<?php

declare(strict_types=1);

namespace App\Portal\Domain\User;

/**
 * Los permisos de una persona: en qué aplicaciones entra y con qué roles.
 *
 *   { "fichajes": ["admin"], "listados": ["usuario"] }
 *
 * Es un valor inmutable que va entero en una columna JSON de la persona, y no
 * una tabla aparte de "usuario × aplicación × rol". En la suite habrá decenas
 * de personas, no miles: con una fila por persona, comprobar los permisos en
 * cada petición es leer UNA fila, y cambiarlos es sustituir un valor, sin
 * altas y bajas de filas sueltas que puedan quedarse a medias.
 *
 * Aquí solo se garantiza la forma (códigos en minúsculas, sin repetir, sin
 * aplicaciones vacías). Que la aplicación y el rol existan lo comprueba el
 * catálogo de la suite (`ApplicationCatalog::validate`), que es quien los
 * conoce.
 */
final readonly class Grants
{
    private const string CODE = '/^[a-z][a-z0-9_-]*$/';

    /**
     * @param array<string, list<string>> $roles aplicación → roles, ya limpio
     */
    private function __construct(private array $roles)
    {
    }

    public static function none(): self
    {
        return new self([]);
    }

    /**
     * @param array<mixed, mixed> $roles aplicación → lista de roles
     */
    public static function fromArray(array $roles): self
    {
        $clean = [];

        foreach ($roles as $application => $list) {
            if (!is_string($application) || 1 !== preg_match(self::CODE, $application)) {
                throw new \InvalidArgumentException(sprintf('"%s" no es un código de aplicación válido.', (string) $application));
            }

            if (!is_array($list)) {
                throw new \InvalidArgumentException(sprintf('Los roles de "%s" tienen que ser una lista.', $application));
            }

            $unique = [];
            foreach ($list as $role) {
                if (!is_string($role) || 1 !== preg_match(self::CODE, $role)) {
                    throw new \InvalidArgumentException(sprintf('"%s" no es un código de rol válido.', is_scalar($role) ? (string) $role : get_debug_type($role)));
                }

                $unique[$role] = true;
            }

            // Una aplicación sin roles es lo mismo que no tener acceso a ella:
            // se quita para que "sin permisos" tenga una sola forma.
            if ([] !== $unique) {
                $sorted = array_keys($unique);
                sort($sorted);
                $clean[$application] = $sorted;
            }
        }

        ksort($clean);

        return new self($clean);
    }

    /** @return list<string> vacía si no entra en esa aplicación */
    public function rolesIn(string $application): array
    {
        return $this->roles[$application] ?? [];
    }

    public function has(string $application, string $role): bool
    {
        return in_array($role, $this->rolesIn($application), true);
    }

    /** @return list<string> las aplicaciones en las que tiene algún rol */
    public function getApplications(): array
    {
        return array_keys($this->roles);
    }

    public function isEmpty(): bool
    {
        return [] === $this->roles;
    }

    public function with(string $application, string $role): self
    {
        $roles = $this->roles;
        $roles[$application] = [...$this->rolesIn($application), $role];

        return self::fromArray($roles);
    }

    /** @return array<string, list<string>> */
    public function toArray(): array
    {
        return $this->roles;
    }
}
