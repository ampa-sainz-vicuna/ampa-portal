<?php

declare(strict_types=1);

namespace App\Portal\Infrastructure\Persistence\Doctrine\Type;

use App\Portal\Domain\User\UserId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;

/**
 * El identificador de una persona, en una columna UUID de verdad.
 *
 * En DBAL 4 los tipos ya NO llevan `getName()`: el nombre se asigna solo en
 * `doctrine.yaml`. Si encuentras tutoriales con `getName()`, son de DBAL 3.
 */
final class UserIdType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getGuidTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof UserId) {
            return $value->toString();
        }

        throw InvalidType::new($value, self::class, ['null', UserId::class]);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?UserId
    {
        if (null === $value || $value instanceof UserId) {
            return $value;
        }

        try {
            return UserId::fromString((string) $value);
        } catch (\InvalidArgumentException $e) {
            throw ValueNotConvertible::new($value, UserId::class, $e->getMessage(), $e);
        }
    }
}
