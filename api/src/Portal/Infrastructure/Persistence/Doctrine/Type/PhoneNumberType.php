<?php

declare(strict_types=1);

namespace App\Portal\Infrastructure\Persistence\Doctrine\Type;

use App\Portal\Domain\Board\PhoneNumber;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;

/**
 * Un teléfono de contacto, ya normalizado.
 */
final class PhoneNumberType extends Type
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

        if ($value instanceof PhoneNumber) {
            return $value->getValue();
        }

        throw InvalidType::new($value, self::class, ['null', PhoneNumber::class]);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?PhoneNumber
    {
        if (null === $value || $value instanceof PhoneNumber) {
            return $value;
        }

        try {
            return PhoneNumber::fromString((string) $value);
        } catch (\InvalidArgumentException $e) {
            // Sin el valor en el mensaje: es un dato personal.
            throw ValueNotConvertible::new('(oculto)', PhoneNumber::class, $e->getMessage(), $e);
        }
    }
}
