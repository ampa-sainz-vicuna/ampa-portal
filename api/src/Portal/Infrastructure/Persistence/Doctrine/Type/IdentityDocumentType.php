<?php

declare(strict_types=1);

namespace App\Portal\Infrastructure\Persistence\Doctrine\Type;

use App\Portal\Domain\Board\IdentityDocument;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;

/**
 * El DNI o NIE, ya normalizado (mayúsculas, sin espacios ni guiones).
 */
final class IdentityDocumentType extends Type
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

        if ($value instanceof IdentityDocument) {
            return $value->getValue();
        }

        throw InvalidType::new($value, self::class, ['null', IdentityDocument::class]);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?IdentityDocument
    {
        if (null === $value || $value instanceof IdentityDocument) {
            return $value;
        }

        try {
            return IdentityDocument::fromString((string) $value);
        } catch (\InvalidArgumentException $e) {
            // Sin el valor en el mensaje: es un dato personal.
            throw ValueNotConvertible::new('(oculto)', IdentityDocument::class, $e->getMessage(), $e);
        }
    }
}
