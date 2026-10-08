<?php

declare(strict_types=1);

namespace App\Tests\Portal\Board;

use App\Portal\Application\Board\AppointBoardMember;
use App\Portal\Application\Board\BoardMemberData;
use App\Portal\Application\Board\EndBoardTerm;
use App\Portal\Application\Board\ListBoard;
use App\Portal\Application\Board\UpdateBoardMember;
use App\Portal\Domain\Board\BoardError;
use App\Portal\Domain\Board\BoardPosition;
use App\Portal\Domain\Board\IdentityDocument;
use App\Portal\Domain\Board\PhoneNumber;
use App\Portal\Domain\Board\SingleHolderRule;
use App\Portal\Domain\User\EmailAddress;
use App\Tests\Double\InMemoryBoardMemberRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** Un titular por cargo (salvo vocales), y la historia al dejarlo. */
final class BoardRulesTest extends TestCase
{
    private InMemoryBoardMemberRepository $members;
    private AppointBoardMember $appoint;

    protected function setUp(): void
    {
        $this->members = new InMemoryBoardMemberRepository();
        $this->appoint = new AppointBoardMember($this->members, new SingleHolderRule($this->members));
    }

    #[Test]
    public function no_hay_dos_titulares_activos_de_presidencia_vicepresidencia_secretaria_ni_tesoreria(): void
    {
        foreach ([BoardPosition::President, BoardPosition::VicePresident, BoardPosition::Secretary, BoardPosition::Treasurer] as $position) {
            ($this->appoint)(self::data('Ana', $position));

            try {
                ($this->appoint)(self::data('Luis', $position));
                self::fail(sprintf('Dejó repetir %s.', $position->value));
            } catch (BoardError) {
                self::addToAssertionCount(1);
            }
        }
    }

    #[Test]
    public function de_vocales_puede_haber_los_que_hagan_falta(): void
    {
        ($this->appoint)(self::data('Ana', BoardPosition::Member));
        ($this->appoint)(self::data('Luis', BoardPosition::Member));
        ($this->appoint)(self::data('Eva', BoardPosition::Member));

        self::assertCount(3, (new ListBoard($this->members))->active());
    }

    #[Test]
    public function tras_dar_de_baja_al_titular_otra_persona_puede_ocupar_el_cargo(): void
    {
        $ana = ($this->appoint)(self::data('Ana', BoardPosition::Secretary));
        (new EndBoardTerm($this->members))($ana->getId(), new \DateTimeImmutable('2026-09-30'));

        ($this->appoint)(self::data('Luis', BoardPosition::Secretary));

        $board = new ListBoard($this->members);
        self::assertSame(['Luis'], array_map(static fn ($m) => $m->getFirstName(), $board->active()));
        self::assertSame(['Ana'], array_map(static fn ($m) => $m->getFirstName(), $board->past()));
    }

    #[Test]
    public function al_editar_se_puede_conservar_el_propio_cargo_pero_no_pasar_a_uno_ocupado(): void
    {
        $update = new UpdateBoardMember($this->members, new SingleHolderRule($this->members));
        $ana = ($this->appoint)(self::data('Ana', BoardPosition::Secretary));
        ($this->appoint)(self::data('Luis', BoardPosition::Treasurer));

        $update($ana->getId(), self::data('Ana María', BoardPosition::Secretary));
        self::assertSame('Ana María', $ana->getFirstName());

        $this->expectException(BoardError::class);
        $update($ana->getId(), self::data('Ana María', BoardPosition::Treasurer));
    }

    #[Test]
    public function dar_de_baja_dos_veces_o_antes_de_empezar_falla(): void
    {
        $end = new EndBoardTerm($this->members);
        $ana = ($this->appoint)(self::data('Ana', BoardPosition::Secretary));

        try {
            $end($ana->getId(), new \DateTimeImmutable('2020-01-01'));
            self::fail('Dejó dar de baja antes del inicio.');
        } catch (\InvalidArgumentException) {
            self::assertTrue($ana->isActive());
        }

        try {
            $end($ana->getId(), new \DateTimeImmutable('2999-01-01'));
            self::fail('Dejó dar de baja con una fecha futura.');
        } catch (\InvalidArgumentException) {
            self::assertTrue($ana->isActive());
        }

        $end($ana->getId(), new \DateTimeImmutable('2026-09-30'));
        $this->expectException(BoardError::class);
        $end($ana->getId(), new \DateTimeImmutable('2026-10-01'));
    }

    #[Test]
    public function los_activos_salen_por_cargo_con_la_presidencia_primero(): void
    {
        ($this->appoint)(self::data('Vocal', BoardPosition::Member));
        ($this->appoint)(self::data('Tesorera', BoardPosition::Treasurer));
        ($this->appoint)(self::data('Presidenta', BoardPosition::President));

        self::assertSame(
            ['Presidenta', 'Tesorera', 'Vocal'],
            array_map(static fn ($m) => $m->getFirstName(), (new ListBoard($this->members))->active()),
        );
    }

    private static function data(string $firstName, BoardPosition $position): BoardMemberData
    {
        return new BoardMemberData(
            $firstName,
            'Prueba Ejemplo',
            IdentityDocument::fromString('12345678Z'),
            'Calle Falsa 1, 28000 Madrid',
            EmailAddress::fromString('prueba@example.com'),
            PhoneNumber::fromString('600123456'),
            $position,
            new \DateTimeImmutable('2026-01-15'),
        );
    }
}
