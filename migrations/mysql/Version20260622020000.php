<?php
declare(strict_types=1);
namespace SqlEngineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260622020000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Data: migrate transport_type values to new OCN/RD/MMD codes; seed service_type on quote';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE quote SET service_type = 'FCL'    WHERE transport_type = 'FCL'");
        $this->addSql("UPDATE quote SET service_type = 'LCL'    WHERE transport_type = 'LCL'");
        $this->addSql("UPDATE quote SET service_type = 'DIRECT' WHERE transport_type = 'AIR'");
        $this->addSql("UPDATE quote SET service_type = 'FTL'    WHERE transport_type = 'RFCL'");
        $this->addSql("UPDATE quote SET service_type = 'FTL'    WHERE transport_type = 'RFTL'");
        $this->addSql("UPDATE quote SET service_type = 'LTL'    WHERE transport_type = 'RLTL'");
        $this->addSql("UPDATE quote SET transport_type = 'OCN' WHERE transport_type IN ('FCL','LCL')");
        $this->addSql("UPDATE quote SET transport_type = 'RD'  WHERE transport_type IN ('RFCL','RFTL','RLTL')");
        $this->addSql("UPDATE quote SET transport_type = 'MMD' WHERE transport_type = 'OTHER'");
        $this->addSql("UPDATE charge SET transport_type = 'OCN' WHERE transport_type IN ('FCL','LCL')");
        $this->addSql("UPDATE charge SET transport_type = 'RD'  WHERE transport_type IN ('RFCL','RFTL','RLTL')");
        $this->addSql("UPDATE charge SET transport_type = 'MMD' WHERE transport_type = 'OTHER'");
        $this->addSql("UPDATE rate SET transport_type = 'OCN' WHERE transport_type IN ('FCL','LCL')");
        $this->addSql("UPDATE rate SET transport_type = 'RD'  WHERE transport_type IN ('RFCL','RFTL','RLTL')");
        $this->addSql("UPDATE rate SET transport_type = 'MMD' WHERE transport_type = 'OTHER'");
        $this->addSql("UPDATE package_type SET transport_type = 'OCN' WHERE transport_type IN ('FCL','LCL')");
        $this->addSql("UPDATE package_type SET transport_type = 'RD'  WHERE transport_type IN ('RFCL','RFTL','RLTL')");
        $this->addSql("UPDATE package_type SET transport_type = 'MMD' WHERE transport_type = 'OTHER'");
        $this->addSql("UPDATE calculation_type SET transport_types = REPLACE(transport_types, 'RFCL', 'RD')");
        $this->addSql("UPDATE calculation_type SET transport_types = REPLACE(transport_types, 'RFTL', 'RD')");
        $this->addSql("UPDATE calculation_type SET transport_types = REPLACE(transport_types, 'RLTL', 'RD')");
        $this->addSql("UPDATE calculation_type SET transport_types = REPLACE(transport_types, 'OTHER', 'MMD')");
        $this->addSql("UPDATE calculation_type SET transport_types = REPLACE(transport_types, 'FCL', 'OCN')");
        $this->addSql("UPDATE calculation_type SET transport_types = REPLACE(transport_types, 'LCL', 'OCN')");
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException();
    }
}
