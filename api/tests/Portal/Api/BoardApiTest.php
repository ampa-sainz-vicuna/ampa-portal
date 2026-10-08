<?php

declare(strict_types=1);

namespace App\Tests\Portal\Api;

use App\Portal\Domain\Board\BoardError;
use App\Portal\Domain\Board\BoardMember;
use App\Portal\Domain\Board\BoardMemberId;
use App\Portal\Domain\Board\BoardMemberRepository;
use App\Portal\Domain\Board\BoardPosition;
use App\Portal\Domain\Board\IdentityDocument;
use App\Portal\Domain\Board\PhoneNumber;
use App\Portal\Domain\User\EmailAddress;
use App\Tests\Support\ApiTestCase;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;

/**
 * La junta: la pestaña *Junta* (solo administradores) y la lectura pública
 * para quien tenga sesión.
 */
final class BoardApiTest extends ApiTestCase
{
    private const string ADMIN = 'admin@example.com';
    private const string DOCUMENT = '12345678Z';
    private const string ADDRESS = 'Calle Falsa 123, 28000 Madrid';
    private const string PHONE = '600123456';

    protected function setUp(): void
    {
        parent::setUp();

        $this->given(self::ADMIN, ['portal' => ['admin']]);
        $this->signedInAs(self::ADMIN);
    }

    /** @return array<string, string> */
    private static function body(string $position = 'secretary', string $firstName = 'Ana', string $startDate = '2026-01-15'): array
    {
        return [
            'firstName' => $firstName,
            'lastName' => 'Prueba Ejemplo',
            'document' => '12.345.678-z',
            'address' => self::ADDRESS,
            'email' => 'Ana.Contacto@Example.com',
            'phone' => '600 12 34 56',
            'position' => $position,
            'startDate' => $startDate,
        ];
    }

    /** @return array<string, mixed> */
    private function appoint(string $position = 'secretary', string $firstName = 'Ana'): array
    {
        $this->request('POST', '/api/admin/junta', self::body($position, $firstName));
        self::assertSame(201, $this->responseStatus());

        return $this->payload();
    }

    #[Test]
    public function un_administrador_da_de_alta_y_los_datos_se_guardan_normalizados(): void
    {
        $member = $this->appoint();

        self::assertSame(self::DOCUMENT, $member['document']);
        self::assertSame('600123456', $member['phone']);
        self::assertSame('ana.contacto@example.com', $member['email']);
        self::assertSame('secretary', $member['position']);
        self::assertSame('2026-01-15', $member['startDate']);
        self::assertNull($member['endDate']);
        self::assertNull($member['userId']);

        $this->request('GET', '/api/admin/junta');
        self::assertSame(200, $this->responseStatus());
        self::assertSame(self::ADDRESS, $this->payload()['active'][0]['address']);
    }

    #[Test]
    public function quien_no_es_administrador_recibe_403_en_todo_lo_de_admin(): void
    {
        $this->given('vocal@example.com', ['listados' => ['usuario']]);
        $this->signedInAs('vocal@example.com');
        $id = BoardMemberId::generate()->toString();

        foreach ([
            ['GET', '/api/admin/junta', null],
            ['POST', '/api/admin/junta', self::body()],
            ['PUT', '/api/admin/junta/'.$id, self::body()],
            ['POST', '/api/admin/junta/'.$id.'/baja', []],
            ['PUT', '/api/admin/junta/'.$id.'/usuario', ['userId' => null]],
        ] as [$method, $path, $body]) {
            $this->request($method, $path, $body);
            self::assertSame(403, $this->responseStatus(), "$method $path");
        }
    }

    #[Test]
    public function sin_sesion_es_401(): void
    {
        $this->client->getCookieJar()->clear();

        $this->request('GET', '/api/admin/junta');
        self::assertSame(401, $this->responseStatus());

        $this->request('GET', '/api/junta');
        self::assertSame(401, $this->responseStatus());
    }

    #[Test]
    public function cualquiera_con_sesion_ve_los_activos_sin_dni_direccion_ni_telefono(): void
    {
        $this->appoint('secretary', 'Ana');
        $old = $this->appoint('treasurer', 'Luis');
        $this->request('POST', '/api/admin/junta/'.$old['id'].'/baja', ['endDate' => '2026-06-30']);
        $this->appoint('president', 'Eva');

        $this->given('vocal@example.com', ['listados' => ['usuario']]);
        $this->signedInAs('vocal@example.com');
        $this->request('GET', '/api/junta');

        self::assertSame(200, $this->responseStatus());
        self::assertSame(
            [
                ['firstName' => 'Eva', 'lastName' => 'Prueba Ejemplo', 'email' => 'ana.contacto@example.com', 'position' => 'president'],
                ['firstName' => 'Ana', 'lastName' => 'Prueba Ejemplo', 'email' => 'ana.contacto@example.com', 'position' => 'secretary'],
            ],
            $this->payload(),
        );

        $raw = (string) $this->client->getResponse()->getContent();
        foreach ([self::DOCUMENT, self::ADDRESS, self::PHONE, 'document', 'address', 'phone'] as $secret) {
            self::assertStringNotContainsString($secret, $raw);
        }
    }

    #[Test]
    public function un_cargo_de_titular_unico_ocupado_es_409_pero_vocales_si_se_repiten(): void
    {
        $this->appoint('secretary', 'Ana');

        $this->request('POST', '/api/admin/junta', self::body('secretary', 'Luis'));
        self::assertSame(409, $this->responseStatus());

        $this->appoint('member', 'Eva');
        $this->appoint('member', 'Pau');
    }

    #[Test]
    public function la_base_de_datos_tambien_impide_dos_titulares_activos(): void
    {
        // Saltándose el caso de uso, como haría una carrera entre dos peticiones.
        $repository = self::getContainer()->get(BoardMemberRepository::class);
        $make = static fn (BoardPosition $position): BoardMember => BoardMember::appoint(
            BoardMemberId::generate(),
            'Ana',
            'Prueba',
            IdentityDocument::fromString(self::DOCUMENT),
            self::ADDRESS,
            EmailAddress::fromString('ana@example.com'),
            PhoneNumber::fromString(self::PHONE),
            $position,
            new \DateTimeImmutable('2026-01-01'),
        );

        $repository->save($make(BoardPosition::Treasurer));
        // Los vocales no tienen límite en la base.
        $repository->save($make(BoardPosition::Member));
        $repository->save($make(BoardPosition::Member));

        $this->expectException(BoardError::class);
        $repository->save($make(BoardPosition::Treasurer));
    }

    #[Test]
    public function el_repositorio_guarda_y_recupera_la_ficha_entera(): void
    {
        $created = $this->appoint('treasurer');
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        $member = self::getContainer()->get(BoardMemberRepository::class)->find(BoardMemberId::fromString($created['id']));

        self::assertNotNull($member);
        self::assertSame(self::DOCUMENT, $member->getDocument()->getValue());
        self::assertSame(BoardPosition::Treasurer, $member->getPosition());
        self::assertSame('2026-01-15', $member->getStartDate()->format('Y-m-d'));
        self::assertTrue($member->isActive());
    }

    #[Test]
    public function dar_de_baja_deja_la_ficha_como_historia_y_libera_el_cargo(): void
    {
        $ana = $this->appoint('secretary', 'Ana');

        $this->request('POST', '/api/admin/junta/'.$ana['id'].'/baja', ['endDate' => '2026-09-30']);
        self::assertSame(200, $this->responseStatus());
        self::assertSame('2026-09-30', $this->payload()['endDate']);

        $this->request('POST', '/api/admin/junta/'.$ana['id'].'/baja', []);
        self::assertSame(409, $this->responseStatus());

        $this->appoint('secretary', 'Luis');
        $this->request('GET', '/api/admin/junta');
        $board = $this->payload();
        self::assertSame(['Luis'], array_column($board['active'], 'firstName'));
        self::assertSame(['Ana'], array_column($board['past'], 'firstName'));
    }

    #[Test]
    public function dar_de_baja_sin_fecha_pone_hoy_y_una_anterior_al_inicio_es_422(): void
    {
        $ana = $this->appoint('secretary', 'Ana');

        $this->request('POST', '/api/admin/junta/'.$ana['id'].'/baja', ['endDate' => '2025-01-01']);
        self::assertSame(422, $this->responseStatus());

        // Una baja es algo que ya ha pasado: no vale una fecha futura.
        $this->request('POST', '/api/admin/junta/'.$ana['id'].'/baja', ['endDate' => '2999-01-01']);
        self::assertSame(422, $this->responseStatus());

        $this->request('POST', '/api/admin/junta/'.$ana['id'].'/baja', []);
        self::assertSame(200, $this->responseStatus());
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', (string) $this->payload()['endDate']);
    }

    #[Test]
    public function edita_la_ficha_entera_y_un_cargo_que_no_existe_es_404(): void
    {
        $ana = $this->appoint('secretary', 'Ana');

        $this->request('PUT', '/api/admin/junta/'.$ana['id'], [...self::body('treasurer', 'Ana Belén'), 'phone' => '+34 611 222 333']);
        self::assertSame(200, $this->responseStatus());
        self::assertSame('Ana Belén', $this->payload()['firstName']);
        self::assertSame('treasurer', $this->payload()['position']);
        self::assertSame('+34611222333', $this->payload()['phone']);

        $this->request('PUT', '/api/admin/junta/'.BoardMemberId::generate()->toString(), self::body());
        self::assertSame(404, $this->responseStatus());

        $this->request('PUT', '/api/admin/junta/no-es-un-id', self::body());
        self::assertSame(404, $this->responseStatus());
    }

    #[Test]
    public function los_datos_que_no_valen_son_422_y_el_mensaje_no_lleva_dni_ni_direccion(): void
    {
        $this->request('POST', '/api/admin/junta', [...self::body(), 'document' => '12345678A']);
        self::assertSame(422, $this->responseStatus());
        $raw = (string) $this->client->getResponse()->getContent();
        self::assertStringNotContainsString('12345678', $raw);
        self::assertStringNotContainsString(self::ADDRESS, $raw);

        foreach ([['phone', '123'], ['email', 'no-es-un-correo'], ['position', 'jefe'], ['startDate', '2026-02-31'], ['address', '   ']] as [$field, $value]) {
            $this->request('POST', '/api/admin/junta', [...self::body(), $field => $value]);
            self::assertSame(422, $this->responseStatus(), $field);
        }
    }

    #[Test]
    public function un_campo_que_falta_es_400(): void
    {
        $body = self::body();
        unset($body['document']);

        $this->request('POST', '/api/admin/junta', $body);

        self::assertSame(400, $this->responseStatus());
    }

    #[Test]
    public function asocia_y_desasocia_un_usuario_de_la_plataforma(): void
    {
        // Antes de la primera petición: dar de alta a alguien en mitad del test
        // (con el kernel ya reiniciado por el cliente) devolvía un 401.
        $user = $this->given('secretaria@example.com', ['listados' => ['usuario']]);
        $ana = $this->appoint('secretary', 'Ana');

        $this->request('PUT', '/api/admin/junta/'.$ana['id'].'/usuario', ['userId' => $user->getId()->toString()]);
        self::assertSame(200, $this->responseStatus());
        self::assertSame($user->getId()->toString(), $this->payload()['userId']);

        $this->request('PUT', '/api/admin/junta/'.$ana['id'].'/usuario', ['userId' => null]);
        self::assertSame(200, $this->responseStatus());
        self::assertNull($this->payload()['userId']);

        $this->request('PUT', '/api/admin/junta/'.$ana['id'].'/usuario', ['userId' => '0192a4b0-0000-7000-8000-000000000099']);
        self::assertSame(404, $this->responseStatus());

        $this->request('PUT', '/api/admin/junta/'.$ana['id'].'/usuario', []);
        self::assertSame(400, $this->responseStatus());
    }
}
