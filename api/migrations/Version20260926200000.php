<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * El calendario escolar común de la suite: un curso por fila, con sus días
 * sin clase en JSON. Vacía al principio: la carga quien gestiona los
 * permisos, en la pantalla *Calendario* del portal.
 */
final class Version20260926200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Calendario escolar común.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE school_years (code VARCHAR(9) NOT NULL, classes_start VARCHAR(10) NOT NULL, classes_end VARCHAR(10) NOT NULL, periods JSONB NOT NULL, PRIMARY KEY (code))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE school_years');
    }
}
