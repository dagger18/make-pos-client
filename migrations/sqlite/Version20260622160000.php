<?php
declare(strict_types=1);
namespace SqlEngineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260622160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Data: seed default chart of accounts for freight forwarding';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("INSERT INTO chart_of_account (code, name, account_type, is_active) VALUES
            ('1100', 'Accounts Receivable',        'ASSET',     1),
            ('1110', 'AR - Ocean Freight',         'ASSET',     1),
            ('1120', 'AR - Air Freight',           'ASSET',     1),
            ('1130', 'AR - Local Charges',         'ASSET',     1),
            ('1140', 'AR - Customs Charges',       'ASSET',     1),
            ('1200', 'Cash and Bank',              'ASSET',     1),
            ('2100', 'Accounts Payable',           'LIABILITY', 1),
            ('2110', 'AP - Carriers',              'LIABILITY', 1),
            ('2120', 'AP - Overseas Agents',       'LIABILITY', 1),
            ('2130', 'AP - Customs Brokers',       'LIABILITY', 1),
            ('2140', 'AP - Truckers',              'LIABILITY', 1),
            ('4100', 'Revenue - Ocean Freight',    'REVENUE',   1),
            ('4110', 'Revenue - Air Freight',      'REVENUE',   1),
            ('4120', 'Revenue - Local Charges',    'REVENUE',   1),
            ('4130', 'Revenue - Customs Charges',  'REVENUE',   1),
            ('4140', 'Revenue - Service Charges',  'REVENUE',   1),
            ('5100', 'COGS - Ocean Freight',       'COST',      1),
            ('5120', 'COGS - Local Charges',       'COST',      1),
            ('5130', 'COGS - Customs / Duty',      'COST',      1),
            ('5140', 'COGS - Service Charges',     'COST',      1),
            ('6900', 'FX Gain / Loss',             'OTHER',     1)
        ");
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException();
    }
}
