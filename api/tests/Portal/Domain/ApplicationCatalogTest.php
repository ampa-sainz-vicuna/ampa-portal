<?php

declare(strict_types=1);

namespace App\Tests\Portal\Domain;

use App\Portal\Domain\Suite\Application;
use App\Portal\Domain\Suite\ApplicationCatalog;
use App\Portal\Domain\User\Grants;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ApplicationCatalogTest extends TestCase
{
    #[Test]
    public function sin_el_portal_y_su_rol_de_admin_no_arranca(): void
    {
        // Nadie podría gestionar permisos: mejor enterarse al desplegar.
        $this->expectException(\InvalidArgumentException::class);

        new ApplicationCatalog([new Application('listados', 'Listados', 'https://l', ['usuario' => 'Usuario'])]);
    }

    #[Test]
    public function acepta_permisos_de_aplicaciones_y_roles_que_existen(): void
    {
        $this->catalog()->validate(Grants::fromArray(['fichajes' => ['admin', 'empleado'], 'portal' => ['admin']]));

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function rechaza_una_aplicacion_que_no_existe(): void
    {
        $this->expectExceptionMessage('No existe la aplicación "tareas".');

        $this->catalog()->validate(Grants::fromArray(['tareas' => ['usuario']]));
    }

    #[Test]
    public function rechaza_un_rol_que_la_aplicacion_no_tiene(): void
    {
        $this->expectExceptionMessage('La aplicación "listados" no tiene el rol "admin".');

        $this->catalog()->validate(Grants::fromArray(['listados' => ['admin']]));
    }

    #[Test]
    public function las_tarjetas_son_las_aplicaciones_con_algun_rol_en_el_orden_del_catalogo_y_sin_el_portal(): void
    {
        $reachable = $this->catalog()->reachableWith(Grants::fromArray([
            'listados' => ['usuario'],
            'fichajes' => ['empleado'],
            'portal' => ['admin'],
        ]));

        self::assertSame(['fichajes', 'listados'], array_map(static fn (Application $a): string => $a->getCode(), $reachable));
    }

    private function catalog(): ApplicationCatalog
    {
        return new ApplicationCatalog([
            new Application('fichajes', 'Fichajes', 'https://f', ['empleado' => 'Empleado', 'admin' => 'Administración']),
            new Application('listados', 'Listados', 'https://l', ['usuario' => 'Usuario']),
            new Application('portal', 'Portal', '', ['admin' => 'Gestiona los permisos']),
        ]);
    }
}
