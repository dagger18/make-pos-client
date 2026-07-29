<?php
declare(strict_types=1);

namespace SqlEngineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260729020000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create table module (restaurant_table)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE restaurant_table (
            id           INT AUTO_INCREMENT NOT NULL,
            location_id  INT NOT NULL,
            name         VARCHAR(64) NOT NULL,
            capacity     INT DEFAULT NULL,
            status       VARCHAR(16) NOT NULL DEFAULT \'available\',
            notes        LONGTEXT DEFAULT NULL,
            created_date DATETIME NOT NULL,
            updated_date DATETIME NOT NULL,
            INDEX IDX_RESTAURANT_TABLE_LOCATION (location_id),
            INDEX IDX_RESTAURANT_TABLE_STATUS (status),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE restaurant_table
            ADD CONSTRAINT FK_RESTAURANT_TABLE_LOCATION FOREIGN KEY (location_id) REFERENCES location (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE restaurant_table DROP FOREIGN KEY FK_RESTAURANT_TABLE_LOCATION');
        $this->addSql('DROP TABLE restaurant_table');
    }
}
