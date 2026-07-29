<?php
declare(strict_types=1);

namespace SqlEngineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260729060000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create inventory module tables (stock_level, stock_movement)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE stock_level (
            id                  INT AUTO_INCREMENT NOT NULL,
            location_id         INT NOT NULL,
            product_id          INT DEFAULT NULL,
            product_name        VARCHAR(255) NOT NULL,
            product_sku         VARCHAR(64) NOT NULL,
            quantity            INT NOT NULL DEFAULT 0,
            low_stock_threshold INT DEFAULT NULL,
            UNIQUE INDEX uq_stock_level_location_product (location_id, product_id),
            INDEX IDX_STOCK_LEVEL_LOCATION (location_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE stock_movement (
            id              INT AUTO_INCREMENT NOT NULL,
            stock_level_id  INT NOT NULL,
            created_by_id   INT DEFAULT NULL,
            type            VARCHAR(16) NOT NULL,
            quantity_delta  INT NOT NULL,
            notes           LONGTEXT DEFAULT NULL,
            created_date    DATETIME NOT NULL,
            updated_date    DATETIME NOT NULL,
            INDEX IDX_STOCK_MOVEMENT_LEVEL (stock_level_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE stock_level
            ADD CONSTRAINT FK_STOCK_LEVEL_LOCATION FOREIGN KEY (location_id) REFERENCES location (id),
            ADD CONSTRAINT FK_STOCK_LEVEL_PRODUCT  FOREIGN KEY (product_id)  REFERENCES product (id) ON DELETE SET NULL');

        $this->addSql('ALTER TABLE stock_movement
            ADD CONSTRAINT FK_STOCK_MOVEMENT_LEVEL      FOREIGN KEY (stock_level_id) REFERENCES stock_level (id) ON DELETE CASCADE,
            ADD CONSTRAINT FK_STOCK_MOVEMENT_CREATED_BY FOREIGN KEY (created_by_id)  REFERENCES `user` (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE stock_movement DROP FOREIGN KEY FK_STOCK_MOVEMENT_LEVEL');
        $this->addSql('ALTER TABLE stock_movement DROP FOREIGN KEY FK_STOCK_MOVEMENT_CREATED_BY');
        $this->addSql('ALTER TABLE stock_level    DROP FOREIGN KEY FK_STOCK_LEVEL_LOCATION');
        $this->addSql('ALTER TABLE stock_level    DROP FOREIGN KEY FK_STOCK_LEVEL_PRODUCT');
        $this->addSql('DROP TABLE stock_movement');
        $this->addSql('DROP TABLE stock_level');
    }
}
