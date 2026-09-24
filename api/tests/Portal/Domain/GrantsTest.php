<?php

declare(strict_types=1);

namespace App\Tests\Portal\Domain;

use App\Portal\Domain\User\Grants;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GrantsTest extends TestCase
{
    #[Test]
    public function ordena_y_quita_repetidos_para_que_los_mismos_permisos_se_guarden_igual(): void
    {
        $grants = Grants::fromArray(['listados' => ['usuario', 'usuario'], 'fichajes' => ['empleado', 'admin']]);

        self::assertSame(['fichajes' => ['admin', 'empleado'], 'listados' => ['usuario']], $grants->toArray());
    }

    #[Test]
    public function una_aplicacion_sin_roles_es_no_tener_acceso(): void
    {
        $grants = Grants::fromArray(['listados' => []]);

        self::assertTrue($grants->isEmpty());
        self::assertSame([], $grants->getApplications());
    }

    #[Test]
    public function dice_que_roles_tiene_en_cada_aplicacion(): void
    {
        $grants = Grants::fromArray(['fichajes' => ['admin']]);

        self::assertSame(['admin'], $grants->rolesIn('fichajes'));
        self::assertSame([], $grants->rolesIn('listados'));
        self::assertTrue($grants->has('fichajes', 'admin'));
        self::assertFalse($grants->has('fichajes', 'empleado'));
    }

    #[Test]
    public function anadir_un_rol_devuelve_otros_permisos_sin_tocar_los_de_antes(): void
    {
        // Inmutable a propósito: Doctrine solo guarda la columna si el objeto
        // es otro (ver GrantsType).
        $before = Grants::fromArray(['listados' => ['usuario']]);
        $after = $before->with('fichajes', 'admin');

        self::assertSame(['listados' => ['usuario']], $before->toArray());
        self::assertSame(['fichajes' => ['admin'], 'listados' => ['usuario']], $after->toArray());
    }

    #[Test]
    public function rechaza_codigos_que_no_son_codigos(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Grants::fromArray(['Fichajes' => ['admin']]);
    }

    #[Test]
    public function rechaza_roles_que_no_son_texto(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Grants::fromArray(['fichajes' => [['admin']]]);
    }
}
