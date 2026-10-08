<?php

declare(strict_types=1);

namespace App\Portal\Infrastructure\Persistence\Doctrine\Type;

use App\Portal\Domain\Board\BoardMemberId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;

/**
 * El identificador de un cargo de la junta, en una columna UUID.
 */
final class BoardMemberIdType extends Type
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

        if ($value instanceof BoardMemberId) {
            return $value->toString();
        }

        throw InvalidType::new($value, self::class, ['null', BoardMemberId::class]);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?BoardMemberId
    {
        if (null === $value || $value instanceof BoardMemberId) {
            return $value;
        }

        try {
            return BoardMemberId::fromString((string) $value);
        } catch (\InvalidArgumentException $e) {
            // Sin el valor en el mensaje: es un dato personal.
            throw ValueNotConvertible::new('(oculto)', BoardMemberId::class, $e->getMessage(), $e);
        }
    }
}
