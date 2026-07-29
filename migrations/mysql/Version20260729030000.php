<?php
declare(strict_types=1);

namespace SqlEngineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260729030000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create kitchen module (kitchen_ticket)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE kitchen_ticket (
            id           INT AUTO_INCREMENT NOT NULL,
            order_id     INT NOT NULL,
            status       VARCHAR(16) NOT NULL DEFAULT \'pending\',
            notes        LONGTEXT DEFAULT NULL,
            created_date DATETIME NOT NULL,
            updated_date DATETIME NOT NULL,
            UNIQUE KEY UNIQ_KITCHEN_TICKET_ORDER (order_id),
            INDEX IDX_KITCHEN_TICKET_STATUS (status),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE kitchen_ticket
            ADD CONSTRAINT FK_KITCHEN_TICKET_ORDER FOREIGN KEY (order_id) REFERENCES pos_order (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE kitchen_ticket DROP FOREIGN KEY FK_KITCHEN_TICKET_ORDER');
        $this->addSql('DROP TABLE kitchen_ticket');
    }
}
