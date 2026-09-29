<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * La ayuda de la suite (Faq): una sola fila, con las preguntas en JSON. Vacía
 * al principio: la carga quien gestiona los permisos, en la pantalla *Ayuda*
 * del portal, desde un fichero faq.json que no está en este repositorio.
 *
 * Solo añade una tabla: la versión anterior del portal sigue funcionando
 * mientras dura el despliegue.
 */
final class Version20260928120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ayuda de la suite.';
    }

    public function up(Schema $schema): void
    {
        // CHECK (id = 1): que nunca pueda haber dos ayudas, ni por error.
        $this->addSql('CREATE TABLE faq (id SMALLINT NOT NULL, notebook_url VARCHAR(500) DEFAULT NULL, contact_email VARCHAR(180) DEFAULT NULL, updated_at VARCHAR(10) DEFAULT NULL, loaded_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, entries JSONB NOT NULL, PRIMARY KEY (id), CONSTRAINT faq_single_row CHECK (id = 1))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE faq');
    }
}
