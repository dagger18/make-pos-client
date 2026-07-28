<?php
declare(strict_types=1);
namespace SqlEngineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260622080000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Data: migrate existing shipment.note values into shipment_note table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("INSERT INTO shipment_note (shipment_id, note_type, body, visible_to, created_at)
            SELECT id, 'INTERNAL', note, 'INTERNAL', COALESCE(created_date, NOW())
            FROM shipment
            WHERE note IS NOT NULL AND TRIM(note) != ''");
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException();
    }
}
