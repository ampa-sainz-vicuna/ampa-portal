<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Cuándo pasó cada persona por la suite por última vez. Vacío para todos al
 * principio: se irá llenando según entren.
 */
final class Version20260926180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Última entrada de cada persona.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD last_seen_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP last_seen_at');
    }
}
