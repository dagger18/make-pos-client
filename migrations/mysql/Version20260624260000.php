<?php
declare(strict_types=1);
namespace SqlEngineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260624260000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Data: seed additional overdue escalation notification rules (Day 1, 14, 30, 60)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("INSERT INTO notification_rule (rule_key, name, trigger_type, trigger_config, recipient_config, channels, template_key, is_active, scope_type, priority, created_date, updated_date) VALUES
('INVOICE_OVERDUE_1D','Invoice Overdue 1 Day','FINANCIAL','{\"event\":\"INVOICE_OVERDUE\",\"days_overdue\":1}','[{\"type\":\"JOB_OPERATOR\"}]','[\"IN_APP\",\"EMAIL\"]','email_invoice_overdue',1,'GLOBAL','NORMAL',NOW(),NOW()),
('INVOICE_OVERDUE_14D','Invoice Overdue 14 Days','FINANCIAL','{\"event\":\"INVOICE_OVERDUE\",\"days_overdue\":14}','[{\"type\":\"JOB_OPERATOR\"}]','[\"IN_APP\",\"EMAIL\"]','email_invoice_overdue',1,'GLOBAL','HIGH',NOW(),NOW()),
('INVOICE_OVERDUE_30D','Invoice Overdue 30 Days','FINANCIAL','{\"event\":\"INVOICE_OVERDUE\",\"days_overdue\":30}','[{\"type\":\"JOB_OPERATOR\"}]','[\"IN_APP\",\"EMAIL\"]','email_invoice_overdue',1,'GLOBAL','URGENT',NOW(),NOW()),
('INVOICE_OVERDUE_60D','Invoice Overdue 60 Days','FINANCIAL','{\"event\":\"INVOICE_OVERDUE\",\"days_overdue\":60}','[{\"type\":\"JOB_OPERATOR\"}]','[\"IN_APP\",\"EMAIL\"]','email_invoice_overdue',1,'GLOBAL','URGENT',NOW(),NOW())
");
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException();
    }
}
