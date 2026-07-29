<?php
declare(strict_types=1);

namespace SqlEngineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260729040000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create loyalty module (loyalty_customer, loyalty_transaction)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE loyalty_customer (
            id           INT AUTO_INCREMENT NOT NULL,
            name         VARCHAR(255) NOT NULL,
            phone        VARCHAR(32) DEFAULT NULL,
            email        VARCHAR(255) DEFAULT NULL,
            points       INT NOT NULL DEFAULT 0,
            created_date DATETIME NOT NULL,
            updated_date DATETIME NOT NULL,
            INDEX IDX_LOYALTY_CUSTOMER_PHONE (phone),
            INDEX IDX_LOYALTY_CUSTOMER_EMAIL (email),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE loyalty_transaction (
            id           INT AUTO_INCREMENT NOT NULL,
            customer_id  INT NOT NULL,
            points       INT NOT NULL,
            type         VARCHAR(16) NOT NULL,
            reference    VARCHAR(255) DEFAULT NULL,
            created_date DATETIME NOT NULL,
            updated_date DATETIME NOT NULL,
            INDEX IDX_LOYALTY_TRANSACTION_CUSTOMER (customer_id),
            INDEX IDX_LOYALTY_TRANSACTION_TYPE (type),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE loyalty_transaction
            ADD CONSTRAINT FK_LOYALTY_TRANSACTION_CUSTOMER FOREIGN KEY (customer_id) REFERENCES loyalty_customer (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE loyalty_transaction DROP FOREIGN KEY FK_LOYALTY_TRANSACTION_CUSTOMER');
        $this->addSql('DROP TABLE loyalty_transaction');
        $this->addSql('DROP TABLE loyalty_customer');
    }
}
