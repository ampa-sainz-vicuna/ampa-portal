<?php

declare(strict_types=1);

namespace App\Portal\Infrastructure\Persistence\Doctrine\Type;

use App\Portal\Domain\User\EmailAddress;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;

/**
 * Un correo, guardado ya en minúsculas (lo garantiza EmailAddress). Por eso
 * basta un índice único normal para que no haya dos altas de la misma persona.
 */
final class EmailAddressType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof EmailAddress) {
            return $value->getValue();
        }

        throw InvalidType::new($value, self::class, ['null', EmailAddress::class]);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?EmailAddress
    {
        if (null === $value || $value instanceof EmailAddress) {
            return $value;
        }

        try {
            return EmailAddress::fromString((string) $value);
        } catch (\InvalidArgumentException $e) {
            throw ValueNotConvertible::new($value, EmailAddress::class, $e->getMessage(), $e);
        }
    }
}
