<?php

declare(strict_types=1);

namespace Ampa\PortalCliente\Portal;

/**
 * Una aplicación de la suite a la que puede ir quien ha entrado. Con la
 * lista, la barra de cada aplicación (`AppShell` de @ampa/ui) pinta el
 * selector para saltar de una a otra sin pasar por el portal.
 */
final readonly class ReachableApplication
{
    public function __construct(
        private string $code,
        private string $name,
        private string $url,
    ) {
    }

    /** El código del catálogo del portal: "fichajes", "tareas"… */
    public function getCode(): string
    {
        return $this->code;
    }

    /** Cómo la ve quien la usa: "Tareas del AMPA". */
    public function getName(): string
    {
        return $this->name;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    /** @return array{code: string, name: string, url: string} */
    public function toArray(): array
    {
        return ['code' => $this->code, 'name' => $this->name, 'url' => $this->url];
    }
}
