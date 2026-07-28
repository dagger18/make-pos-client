<?php
declare(strict_types=1);

namespace SqlEngineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260728220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create sales module tables (pos_order, order_item, order_payment)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE pos_order (
            id              INT AUTO_INCREMENT NOT NULL,
            location_id     INT NOT NULL,
            created_by_id   INT DEFAULT NULL,
            status          VARCHAR(16) NOT NULL DEFAULT \'open\',
            notes           LONGTEXT DEFAULT NULL,
            subtotal        NUMERIC(20, 6) NOT NULL DEFAULT 0,
            discount_amount NUMERIC(20, 6) NOT NULL DEFAULT 0,
            tax_amount      NUMERIC(20, 6) NOT NULL DEFAULT 0,
            total           NUMERIC(20, 6) NOT NULL DEFAULT 0,
            paid_amount     NUMERIC(20, 6) NOT NULL DEFAULT 0,
            created_date    DATETIME NOT NULL,
            updated_date    DATETIME NOT NULL,
            INDEX IDX_POS_ORDER_LOCATION (location_id),
            INDEX IDX_POS_ORDER_STATUS (status),
            INDEX IDX_POS_ORDER_CREATED (created_date),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE order_item (
            id           INT AUTO_INCREMENT NOT NULL,
            order_id     INT NOT NULL,
            product_id   INT DEFAULT NULL,
            product_name VARCHAR(255) NOT NULL,
            product_sku  VARCHAR(64) NOT NULL,
            unit_price   NUMERIC(20, 6) NOT NULL,
            quantity     INT NOT NULL DEFAULT 1,
            modifiers    JSON DEFAULT NULL,
            notes        VARCHAR(255) DEFAULT NULL,
            item_total   NUMERIC(20, 6) NOT NULL,
            INDEX IDX_ORDER_ITEM_ORDER (order_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE order_payment (
            id        INT AUTO_INCREMENT NOT NULL,
            order_id  INT NOT NULL,
            method    VARCHAR(16) NOT NULL DEFAULT \'cash\',
            amount    NUMERIC(20, 6) NOT NULL,
            reference VARCHAR(100) DEFAULT NULL,
            paid_at   DATETIME NOT NULL,
            INDEX IDX_ORDER_PAYMENT_ORDER (order_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE pos_order
            ADD CONSTRAINT FK_POS_ORDER_LOCATION FOREIGN KEY (location_id)   REFERENCES location (id),
            ADD CONSTRAINT FK_POS_ORDER_USER     FOREIGN KEY (created_by_id) REFERENCES `user` (id) ON DELETE SET NULL');

        $this->addSql('ALTER TABLE order_item
            ADD CONSTRAINT FK_ORDER_ITEM_ORDER FOREIGN KEY (order_id) REFERENCES pos_order (id) ON DELETE CASCADE');

        $this->addSql('ALTER TABLE order_payment
            ADD CONSTRAINT FK_ORDER_PAYMENT_ORDER FOREIGN KEY (order_id) REFERENCES pos_order (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE order_item    DROP FOREIGN KEY FK_ORDER_ITEM_ORDER');
        $this->addSql('ALTER TABLE order_payment DROP FOREIGN KEY FK_ORDER_PAYMENT_ORDER');
        $this->addSql('ALTER TABLE pos_order     DROP FOREIGN KEY FK_POS_ORDER_LOCATION');
        $this->addSql('ALTER TABLE pos_order     DROP FOREIGN KEY FK_POS_ORDER_USER');
        $this->addSql('DROP TABLE order_payment');
        $this->addSql('DROP TABLE order_item');
        $this->addSql('DROP TABLE pos_order');
    }
}
