<?php

declare(strict_types=1);

namespace Ampa\PortalCliente\Tests;

use Ampa\PortalCliente\Portal\MetadataServerIdentity;
use Ampa\PortalCliente\Portal\PortalUnavailable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Cómo se pide el token de este servidor al servidor de metadatos de Cloud
 * Run. Que Google lo emite así solo se puede comprobar en Cloud Run.
 */
final class MetadataServerIdentityTest extends TestCase
{
    #[Test]
    public function lo_pide_para_el_portal_con_el_correo_de_la_cuenta_y_una_vez_por_peticion(): void
    {
        $requests = 0;
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests): MockResponse {
            ++$requests;
            self::assertSame('GET', $method);
            self::assertSame(
                'http://metadata.google.internal/computeMetadata/v1/instance/service-accounts/default/identity?audience=https://ampa-portal-123.europe-west1.run.app&format=full',
                $url,
            );
            self::assertContains('Metadata-Flavor: Google', $options['headers']);

            return new MockResponse("el-token\n");
        });

        $identity = new MetadataServerIdentity($client, 'https://ampa-portal-123.europe-west1.run.app/');

        self::assertSame('el-token', $identity->token());
        self::assertSame('el-token', $identity->token());
        self::assertSame(1, $requests);
    }

    #[Test]
    public function fuera_de_cloud_run_el_portal_no_esta_disponible(): void
    {
        $identity = new MetadataServerIdentity(new MockHttpClient(new MockResponse(info: ['error' => 'no existe metadata.google.internal'])), 'http://portal');

        $this->expectException(PortalUnavailable::class);

        $identity->token();
    }
}
