<?php

declare(strict_types=1);

namespace App\Tests\Portal\Domain;

use App\Portal\Domain\Help\Faq;
use App\Portal\Domain\Help\FaqEntry;
use App\Portal\Domain\Help\FaqError;
use App\Portal\Domain\Suite\Application;
use App\Portal\Domain\Suite\ApplicationCatalog;
use App\Portal\Domain\User\Grants;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FaqTest extends TestCase
{
    #[Test]
    public function las_generales_las_ve_todo_el_mundo(): void
    {
        self::assertTrue($this->entry('general')->isVisibleWith(Grants::fromArray(['listados' => ['usuario']])));
    }

    #[Test]
    public function las_de_una_aplicacion_solo_quien_entra_en_ella(): void
    {
        $entry = $this->entry('facturacion');

        self::assertTrue($entry->isVisibleWith(Grants::fromArray(['facturacion' => ['usuario']])));
        self::assertFalse($entry->isVisibleWith(Grants::fromArray(['listados' => ['usuario']])));
    }

    #[Test]
    public function con_roles_solo_quien_tiene_alguno_de_ellos(): void
    {
        $entry = $this->entry('fichajes', ['admin']);

        self::assertTrue($entry->isVisibleWith(Grants::fromArray(['fichajes' => ['admin', 'empleado']])));
        self::assertFalse($entry->isVisibleWith(Grants::fromArray(['fichajes' => ['empleado']])));
        // El rol tiene que ser en ESA aplicación, no en otra.
        self::assertFalse($entry->isVisibleWith(Grants::fromArray(['portal' => ['admin'], 'fichajes' => ['empleado']])));
    }

    #[Test]
    public function limpia_los_espacios_las_palabras_clave_vacias_y_el_manual_vacio(): void
    {
        $entry = FaqEntry::of(' cerrar-mes ', 'facturacion', [], ' ¿Cómo cierro el mes? ', "Paso 1.\nPaso 2.\n", ['cierre', ' ', 'cierre', ' mes '], '');

        self::assertSame('cerrar-mes', $entry->getId());
        self::assertSame('¿Cómo cierro el mes?', $entry->getQuestion());
        self::assertSame("Paso 1.\nPaso 2.", $entry->getAnswer());
        self::assertSame(['cierre', 'mes'], $entry->getKeywords());
        self::assertNull($entry->getManual());
    }

    /**
     * @return iterable<string, array{\Closure(): mixed, string}>
     */
    public static function badEntries(): iterable
    {
        yield 'sin identificador' => [static fn () => FaqEntry::of(' ', 'general', [], 'P', 'R', [], null), 'sin identificador'];
        yield 'sin pregunta' => [static fn () => FaqEntry::of('a', 'general', [], '  ', 'R', [], null), 'falta la pregunta'];
        yield 'sin respuesta' => [static fn () => FaqEntry::of('a', 'general', [], 'P', '', [], null), 'falta la respuesta'];
        yield 'respuesta enorme' => [static fn () => FaqEntry::of('a', 'general', [], 'P', str_repeat('x', 10001), [], null), 'más de 10000'];
        yield 'general con roles' => [static fn () => FaqEntry::of('a', 'general', ['admin'], 'P', 'R', [], null), 'no llevan roles'];
    }

    /**
     * @param \Closure(): mixed $build
     */
    #[Test]
    #[DataProvider('badEntries')]
    public function una_pregunta_mal_hecha_no_se_crea(\Closure $build, string $message): void
    {
        $this->expectException(FaqError::class);
        $this->expectExceptionMessage($message);

        $build();
    }

    #[Test]
    public function se_carga_con_sus_datos_y_cuenta_las_preguntas_de_cada_aplicacion(): void
    {
        $faq = $this->publish([$this->entry('general', id: 'a'), $this->entry('facturacion', id: 'b'), $this->entry('facturacion', id: 'c')]);

        self::assertSame(['general' => 1, 'facturacion' => 2], $faq->countByApplication());
        self::assertSame('https://notebooklm.example.com/cuaderno', $faq->getNotebookUrl());
        self::assertSame('ayuda@example.com', $faq->getContactEmail());
        self::assertSame('2026-09-28', $faq->getUpdatedAt());
        self::assertSame(['a', 'b', 'c'], array_map(static fn (FaqEntry $entry): string => $entry->getId(), $faq->getEntries()));
    }

    #[Test]
    public function cargarla_otra_vez_la_sustituye_entera(): void
    {
        $faq = $this->publish([$this->entry('general', id: 'a'), $this->entry('facturacion', id: 'b')]);

        $faq->replace([$this->entry('listados', id: 'z')], null, null, null, self::catalog(), new \DateTimeImmutable('2026-10-01 10:00'));

        self::assertSame(['listados' => 1], $faq->countByApplication());
        self::assertNull($faq->getNotebookUrl());
        self::assertEquals(new \DateTimeImmutable('2026-10-01 10:00'), $faq->getLoadedAt());
    }

    #[Test]
    public function si_algo_no_vale_no_cambia_nada(): void
    {
        $faq = $this->publish([$this->entry('general', id: 'a')]);

        try {
            $faq->replace([$this->entry('general', id: 'b')], 'http://inseguro.example.com', null, null, self::catalog(), new \DateTimeImmutable());
            self::fail('Tenía que rechazar el enlace sin https.');
        } catch (FaqError) {
        }

        self::assertSame(['general' => 1], $faq->countByApplication());
        self::assertSame('https://notebooklm.example.com/cuaderno', $faq->getNotebookUrl());
    }

    #[Test]
    public function da_a_cada_uno_solo_lo_suyo_en_el_orden_del_fichero(): void
    {
        $faq = $this->publish([
            $this->entry('facturacion', id: 'f'),
            $this->entry('general', id: 'g'),
            $this->entry('fichajes', ['admin'], id: 'admin'),
            $this->entry('fichajes', id: 'todos'),
        ]);

        $visible = $faq->entriesVisibleWith(Grants::fromArray(['fichajes' => ['empleado']]));

        self::assertSame(['g', 'todos'], array_map(static fn (FaqEntry $entry): string => $entry->getId(), $visible));
    }

    /**
     * @return iterable<string, array{list<FaqEntry>, string|null, string|null, string|null, string}>
     */
    public static function badFaqs(): iterable
    {
        $entry = static fn (string $id, string $application = 'general', array $roles = []): FaqEntry => FaqEntry::of($id, $application, $roles, 'P', 'R', [], null);

        yield 'ids repetidos' => [[$entry('a'), $entry('a')], null, null, null, 'repetido'];
        yield 'aplicación que no existe' => [[$entry('a', 'cocina')], null, null, null, 'no existe la aplicación "cocina"'];
        yield 'rol que no existe' => [[$entry('a', 'listados', ['admin'])], null, null, null, 'no tiene el rol "admin"'];
        yield 'cuaderno sin https' => [[], 'http://notebooklm.example.com', null, null, 'https'];
        yield 'cuaderno que no es una dirección' => [[], 'javascript:alert(1)', null, null, 'https'];
        yield 'correo que no es un correo' => [[], null, 'ayuda', null, 'contactEmail'];
        yield 'fecha imposible' => [[], null, null, '2026-02-30', 'AAAA-MM-DD'];
    }

    /**
     * @param list<FaqEntry> $entries
     */
    #[Test]
    #[DataProvider('badFaqs')]
    public function una_ayuda_que_no_cuadra_no_se_carga(array $entries, ?string $url, ?string $email, ?string $date, string $message): void
    {
        $this->expectException(FaqError::class);
        $this->expectExceptionMessage($message);

        Faq::publish($entries, $url, $email, $date, self::catalog(), new \DateTimeImmutable());
    }

    #[Test]
    public function sin_cuaderno_ni_correo_ni_fecha_tambien_vale(): void
    {
        $faq = Faq::publish([], '', ' ', null, self::catalog(), new \DateTimeImmutable());

        self::assertNull($faq->getNotebookUrl());
        self::assertNull($faq->getContactEmail());
        self::assertNull($faq->getUpdatedAt());
    }

    /**
     * @param list<string> $roles
     */
    private function entry(string $application, array $roles = [], string $id = 'pregunta'): FaqEntry
    {
        return FaqEntry::of($id, $application, $roles, '¿Cómo…?', 'Así.', ['palabra'], '06');
    }

    /**
     * @param list<FaqEntry> $entries
     */
    private function publish(array $entries): Faq
    {
        return Faq::publish($entries, 'https://notebooklm.example.com/cuaderno', 'Ayuda@Example.com', '2026-09-28', self::catalog(), new \DateTimeImmutable('2026-09-28 12:00'));
    }

    private static function catalog(): ApplicationCatalog
    {
        return new ApplicationCatalog([
            new Application('fichajes', 'Fichajes', 'http://localhost:5173', ['empleado' => 'Empleado', 'admin' => 'Administración']),
            new Application('listados', 'Listados', 'http://localhost:5174', ['usuario' => 'Usuario']),
            new Application('facturacion', 'Facturación', 'http://localhost:5175', ['usuario' => 'Usuario']),
            new Application('portal', 'Portal', '', ['admin' => 'Gestiona los permisos']),
        ]);
    }
}
