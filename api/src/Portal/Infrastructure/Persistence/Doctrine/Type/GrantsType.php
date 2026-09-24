<?php

declare(strict_types=1);

namespace App\Portal\Infrastructure\Persistence\Doctrine\Type;

use App\Portal\Domain\User\Grants;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;

/**
 * Los permisos de una persona, en una columna JSON: `{"listados": ["usuario"]}`.
 * El porqué de una columna y no una tabla, en Grants.
 *
 * Grants es inmutable: cambiar un permiso crea otro objeto, y Doctrine ve que
 * no es el mismo y guarda la columna. Si Grants se modificara por dentro,
 * Doctrine no se enteraría y el cambio se perdería sin avisar.
 */
final class GrantsType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getJsonTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!$value instanceof Grants) {
            throw InvalidType::new($value, self::class, ['null', Grants::class]);
        }

        // Un objeto vacío y no una lista: "{}" y no "[]", para que la columna
        // tenga siempre la misma forma aunque la persona no tenga permisos.
        return json_encode((object) $value->toArray(), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Grants
    {
        if (null === $value || $value instanceof Grants) {
            return $value;
        }

        try {
            $data = is_array($value) ? $value : json_decode((string) $value, true, 512, \JSON_THROW_ON_ERROR);

            return Grants::fromArray(is_array($data) ? $data : []);
        } catch (\JsonException|\InvalidArgumentException $e) {
            throw ValueNotConvertible::new($value, Grants::class, $e->getMessage(), $e);
        }
    }
}
