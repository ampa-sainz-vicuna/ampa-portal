<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Las personas de la suite y sus permisos.
 *
 * Nadie dado de alta: el primer administrador se da con el comando
 * `app:permisos:dar` (README, "Primer arranque"). Así esta migración no lleva
 * correos de nadie dentro del repositorio, que es público.
 */
final class Version20260924180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Personas de la suite con sus permisos, su segundo correo y a dónde van sus avisos.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE users (
                id UUID NOT NULL,
                email VARCHAR(180) NOT NULL,
                name VARCHAR(120) NOT NULL,
                active BOOLEAN NOT NULL,
                grants JSON NOT NULL,
                secondary_email VARCHAR(180) DEFAULT NULL,
                notify VARCHAR(10) NOT NULL,
                PRIMARY KEY (id),
                CONSTRAINT chk_users_notify CHECK (notify IN ('primary', 'secondary', 'both')),
                -- Avisos al segundo correo sin segundo correo: nunca le llegaría nada.
                CONSTRAINT chk_users_secondary CHECK (notify = 'primary' OR secondary_email IS NOT NULL)
            )
            SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_users_email ON users (email)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE users');
    }
}
