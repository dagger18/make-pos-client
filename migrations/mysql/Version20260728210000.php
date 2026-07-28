<?php

declare(strict_types=1);

namespace SqlEngineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260728210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create catalog module tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE product_category (id INT AUTO_INCREMENT NOT NULL, location_id INT NOT NULL, parent_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, position INT NOT NULL, INDEX IDX_CDFC735364D218E (location_id), INDEX IDX_CDFC7353727ACA70 (parent_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE product (id INT AUTO_INCREMENT NOT NULL, location_id INT NOT NULL, category_id INT DEFAULT NULL, image_id INT DEFAULT NULL, sku VARCHAR(64) NOT NULL, name VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, price NUMERIC(20, 6) NOT NULL, cost NUMERIC(20, 6) DEFAULT NULL, type VARCHAR(8) NOT NULL, active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_D34A04AD64D218E (location_id), INDEX IDX_D34A04AD12469DE2 (category_id), INDEX IDX_D34A04AD3DA5256D (image_id), UNIQUE INDEX uq_product_sku_location (sku, location_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE modifier_group (id INT AUTO_INCREMENT NOT NULL, location_id INT NOT NULL, name VARCHAR(255) NOT NULL, required TINYINT(1) NOT NULL, min INT NOT NULL, max INT DEFAULT NULL, position INT NOT NULL, INDEX IDX_DF4B9F764D218E (location_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE modifier (id INT AUTO_INCREMENT NOT NULL, group_id INT NOT NULL, name VARCHAR(255) NOT NULL, price_delta NUMERIC(20, 6) NOT NULL, position INT NOT NULL, INDEX IDX_793C72A5FE54D947 (group_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE product_modifier_group (product_id INT NOT NULL, modifier_group_id INT NOT NULL, INDEX IDX_B0D1E4584584665A (product_id), INDEX IDX_B0D1E4583BC96E31 (modifier_group_id), PRIMARY KEY(product_id, modifier_group_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE product_modifier (id INT AUTO_INCREMENT NOT NULL, product_id INT NOT NULL, name VARCHAR(255) NOT NULL, price_delta NUMERIC(20, 6) NOT NULL, position INT NOT NULL, INDEX IDX_86DFD6074584665A (product_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE product_category ADD CONSTRAINT FK_CDFC735364D218E FOREIGN KEY (location_id) REFERENCES location (id)');
        $this->addSql('ALTER TABLE product_category ADD CONSTRAINT FK_CDFC7353727ACA70 FOREIGN KEY (parent_id) REFERENCES product_category (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE product ADD CONSTRAINT FK_D34A04AD64D218E FOREIGN KEY (location_id) REFERENCES location (id)');
        $this->addSql('ALTER TABLE product ADD CONSTRAINT FK_D34A04AD12469DE2 FOREIGN KEY (category_id) REFERENCES product_category (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE product ADD CONSTRAINT FK_D34A04AD3DA5256D FOREIGN KEY (image_id) REFERENCES media (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE modifier_group ADD CONSTRAINT FK_DF4B9F764D218E FOREIGN KEY (location_id) REFERENCES location (id)');
        $this->addSql('ALTER TABLE modifier ADD CONSTRAINT FK_793C72A5FE54D947 FOREIGN KEY (group_id) REFERENCES modifier_group (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE product_modifier_group ADD CONSTRAINT FK_B0D1E4584584665A FOREIGN KEY (product_id) REFERENCES product (id)');
        $this->addSql('ALTER TABLE product_modifier_group ADD CONSTRAINT FK_B0D1E4583BC96E31 FOREIGN KEY (modifier_group_id) REFERENCES modifier_group (id)');
        $this->addSql('ALTER TABLE product_modifier ADD CONSTRAINT FK_86DFD6074584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product_category DROP FOREIGN KEY FK_CDFC735364D218E');
        $this->addSql('ALTER TABLE product_category DROP FOREIGN KEY FK_CDFC7353727ACA70');
        $this->addSql('ALTER TABLE product DROP FOREIGN KEY FK_D34A04AD64D218E');
        $this->addSql('ALTER TABLE product DROP FOREIGN KEY FK_D34A04AD12469DE2');
        $this->addSql('ALTER TABLE product DROP FOREIGN KEY FK_D34A04AD3DA5256D');
        $this->addSql('ALTER TABLE modifier_group DROP FOREIGN KEY FK_DF4B9F764D218E');
        $this->addSql('ALTER TABLE modifier DROP FOREIGN KEY FK_793C72A5FE54D947');
        $this->addSql('ALTER TABLE product_modifier_group DROP FOREIGN KEY FK_B0D1E4584584665A');
        $this->addSql('ALTER TABLE product_modifier_group DROP FOREIGN KEY FK_B0D1E4583BC96E31');
        $this->addSql('ALTER TABLE product_modifier DROP FOREIGN KEY FK_86DFD6074584665A');
        $this->addSql('DROP TABLE product_category');
        $this->addSql('DROP TABLE product');
        $this->addSql('DROP TABLE modifier_group');
        $this->addSql('DROP TABLE modifier');
        $this->addSql('DROP TABLE product_modifier_group');
        $this->addSql('DROP TABLE product_modifier');
    }
}
