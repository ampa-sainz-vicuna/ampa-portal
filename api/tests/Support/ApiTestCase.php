<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Portal\Domain\User\EmailAddress;
use App\Portal\Domain\User\Grants;
use App\Portal\Domain\User\NotificationTarget;
use App\Portal\Domain\User\User;
use App\Portal\Domain\User\UserId;
use App\Portal\Domain\User\UserRepository;
use App\Portal\Infrastructure\Security\SessionCookie;
use App\Portal\Infrastructure\Security\SessionTokens;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;

/**
 * Lo común a los tests de la API: una base de datos vacía en cada test, dar
 * de alta a alguien y "haber entrado" con su cookie.
 */
abstract class ApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->getConnection()->executeStatement('DELETE FROM users');
        $entityManager->clear();
    }

    /**
     * @param array<string, list<string>> $grants
     */
    protected function given(
        string $email,
        array $grants,
        bool $active = true,
        ?string $secondaryEmail = null,
        NotificationTarget $notify = NotificationTarget::Primary,
    ): User {
        $user = User::register(UserId::generate(), EmailAddress::fromString($email), ucfirst(strstr($email, '@', true) ?: $email), Grants::fromArray($grants));
        $user->changeContact(null === $secondaryEmail ? null : EmailAddress::fromString($secondaryEmail), $notify);
        if (!$active) {
            $user->deactivate();
        }

        self::getContainer()->get(UserRepository::class)->save($user);

        return $user;
    }

    /** La cookie que habría puesto el portal al entrar con Google. */
    protected function signedInAs(string $email): void
    {
        $this->client->getCookieJar()->set(new Cookie(SessionCookie::NAME, $this->tokenFor($email)));
    }

    protected function tokenFor(string $email): string
    {
        return self::getContainer()->get(SessionTokens::class)->issue(EmailAddress::fromString($email));
    }

    /**
     * @param array<string, mixed>|null $body
     * @param array<string, string>     $server cabeceras, al estilo de $_SERVER (HTTP_…)
     */
    protected function request(string $method, string $path, ?array $body = null, array $server = []): void
    {
        $this->client->request(
            $method,
            $path,
            server: ['CONTENT_TYPE' => 'application/json', ...$server],
            content: null === $body ? null : (string) json_encode($body),
        );
    }

    protected function responseStatus(): int
    {
        return $this->client->getResponse()->getStatusCode();
    }

    /** @return array<mixed> */
    protected function payload(): array
    {
        return json_decode((string) $this->client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
    }

    protected function reload(User $user): User
    {
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        return self::getContainer()->get(UserRepository::class)->find($user->getId())
            ?? throw new \LogicException('Ha desaparecido.');
    }
}
