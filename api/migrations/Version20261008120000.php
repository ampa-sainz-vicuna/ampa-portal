<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Los cargos de la junta del AMPA (BoardMember), con su historia: al dejar un
 * cargo se pone fecha de fin, no se borra la fila.
 *
 * Solo añade una tabla: la versión anterior del portal sigue funcionando
 * mientras dura el despliegue. Vacía: la junta se carga desde la pestaña
 * *Junta* (datos personales, que no van en este repositorio público).
 */
final class Version20261008120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cargos de la junta del AMPA, con historia.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE board_members (
                id UUID NOT NULL,
                first_name VARCHAR(120) NOT NULL,
                last_name VARCHAR(180) NOT NULL,
                document VARCHAR(9) NOT NULL,
                address VARCHAR(300) NOT NULL,
                email VARCHAR(180) NOT NULL,
                phone VARCHAR(16) NOT NULL,
                board_position VARCHAR(20) NOT NULL,
                start_date DATE NOT NULL,
                end_date DATE DEFAULT NULL,
                user_id UUID DEFAULT NULL,
                PRIMARY KEY (id),
                CONSTRAINT chk_board_position CHECK (board_position IN ('president', 'vice_president', 'secretary', 'treasurer', 'member')),
                CONSTRAINT chk_board_dates CHECK (end_date IS NULL OR end_date >= start_date),
                -- Las personas de la suite no se borran (se desactivan); si alguna vez
                -- se borrase una, el cargo se queda, sin usuario asociado.
                CONSTRAINT fk_board_members_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
            )
            SQL);

        // La regla de la junta, también en la base: como mucho un titular
        // activo (sin fecha de fin) en presidencia, vicepresidencia, secretaría
        // y tesorería. Los vocales, los que hagan falta. Aunque dos altas
        // lleguen a la vez, la segunda falla.
        $this->addSql("CREATE UNIQUE INDEX uniq_board_single_holder ON board_members (board_position) WHERE end_date IS NULL AND board_position <> 'member'");
        $this->addSql('CREATE INDEX idx_board_members_user ON board_members (user_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE board_members');
    }
}
