<?php

declare(strict_types=1);

namespace SqlEngineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260728200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename branch table and FK columns to location';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('RENAME TABLE branch TO location');
        $this->addSql('RENAME TABLE user_branch TO user_location');
        $this->addSql('ALTER TABLE department CHANGE branch_id location_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE sales_target CHANGE branch_id location_id INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('RENAME TABLE location TO branch');
        $this->addSql('RENAME TABLE user_location TO user_branch');
        $this->addSql('ALTER TABLE department CHANGE location_id branch_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE sales_target CHANGE location_id branch_id INT DEFAULT NULL');
    }
}
