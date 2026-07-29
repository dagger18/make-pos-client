<?php
declare(strict_types=1);

namespace SqlEngineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260729010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create shift module table (shift)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE shift (
            id              INT AUTO_INCREMENT NOT NULL,
            location_id     INT NOT NULL,
            cashier_id      INT DEFAULT NULL,
            status          VARCHAR(8) NOT NULL DEFAULT \'open\',
            opening_amount  NUMERIC(20, 6) NOT NULL DEFAULT 0,
            closing_amount  NUMERIC(20, 6) DEFAULT NULL,
            notes           LONGTEXT DEFAULT NULL,
            opened_at       DATETIME NOT NULL,
            closed_at       DATETIME DEFAULT NULL,
            created_date    DATETIME NOT NULL,
            updated_date    DATETIME NOT NULL,
            INDEX IDX_SHIFT_LOCATION (location_id),
            INDEX IDX_SHIFT_STATUS (status),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE shift
            ADD CONSTRAINT FK_SHIFT_LOCATION FOREIGN KEY (location_id) REFERENCES location (id),
            ADD CONSTRAINT FK_SHIFT_CASHIER  FOREIGN KEY (cashier_id)  REFERENCES `user` (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shift DROP FOREIGN KEY FK_SHIFT_LOCATION');
        $this->addSql('ALTER TABLE shift DROP FOREIGN KEY FK_SHIFT_CASHIER');
        $this->addSql('DROP TABLE shift');
    }
}
