<?php
declare(strict_types=1);
namespace SqlEngineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260626100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Data: seed GLEC v3 emission factors for OCN, AIR, RD, RAL transport modes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("INSERT INTO emission_factor
            (transport_mode, vehicle_type, size_class, ef_ttw, ef_wtw, methodology, effective_from, source, created_at)
            VALUES
            ('OCN', 'CONTAINER_SHIP', '>8000TEU',      0.005670, 0.006200, 'GLEC_V3', '2024-01-01', 'GLEC Framework v3 Table 4.2', datetime('now')),
            ('OCN', 'CONTAINER_SHIP', '4000-8000TEU',  0.008000, 0.008750, 'GLEC_V3', '2024-01-01', 'GLEC Framework v3 Table 4.2', datetime('now')),
            ('OCN', 'CONTAINER_SHIP', '<4000TEU',       0.011000, 0.012000, 'GLEC_V3', '2024-01-01', 'GLEC Framework v3 Table 4.2', datetime('now')),
            ('AIR', 'AIRCRAFT',       'BELLY_CARGO',    0.602000, 0.670000, 'GLEC_V3', '2024-01-01', 'GLEC Framework v3 Table 4.2', datetime('now')),
            ('AIR', 'AIRCRAFT',       'FREIGHTER',      0.786000, 0.873000, 'GLEC_V3', '2024-01-01', 'GLEC Framework v3 Table 4.2', datetime('now')),
            ('RD',  'TRUCK_RIGID',    '>34T',           0.062000, 0.072000, 'GLEC_V3', '2024-01-01', 'GLEC Framework v3 Table 4.2', datetime('now')),
            ('RD',  'TRUCK_RIGID',    '7.5-12T',        0.170000, 0.196000, 'GLEC_V3', '2024-01-01', 'GLEC Framework v3 Table 4.2', datetime('now')),
            ('RAL', 'TRAIN',          'FREIGHT',        0.028000, 0.035000, 'GLEC_V3', '2024-01-01', 'GLEC Framework v3 Table 4.2', datetime('now'))
        ");
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException();
    }
}
