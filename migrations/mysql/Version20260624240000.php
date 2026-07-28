<?php
declare(strict_types=1);
namespace SqlEngineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260624240000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Data: seed default notification rules and email templates';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("INSERT INTO notification_rule (rule_key, name, trigger_type, trigger_config, recipient_config, channels, template_key, is_active, scope_type, priority, created_date, updated_date) VALUES
('MILESTONE_VESSEL_DEPARTED','Vessel Departed','MILESTONE','{\"milestone_code\":\"VESSEL_DEPARTED\"}','[{\"type\":\"JOB_OPERATOR\"}]','[\"IN_APP\",\"EMAIL\"]','email_milestone_vessel_departed',1,'GLOBAL','NORMAL',NOW(),NOW()),
('MILESTONE_VESSEL_ARRIVED','Vessel Arrived','MILESTONE','{\"milestone_code\":\"VESSEL_ARRIVED\"}','[{\"type\":\"JOB_OPERATOR\"}]','[\"IN_APP\",\"EMAIL\"]','email_milestone_vessel_arrived',1,'GLOBAL','NORMAL',NOW(),NOW()),
('MILESTONE_DELIVERED','Cargo Delivered','MILESTONE','{\"milestone_code\":\"DELIVERED\"}','[{\"type\":\"JOB_OPERATOR\"}]','[\"IN_APP\",\"EMAIL\"]','email_milestone_delivered',1,'GLOBAL','HIGH',NOW(),NOW()),
('STATUS_CHANGE','Job Status Changed','STATUS_CHANGE','{}','[{\"type\":\"JOB_OPERATOR\"}]','[\"IN_APP\"]',NULL,1,'GLOBAL','NORMAL',NOW(),NOW()),
('CUTOFF_SI_48H','SI Cutoff in 48h','DEADLINE','{\"deadline_field\":\"booking.cutoff_si\",\"hours_before\":48}','[{\"type\":\"JOB_OPERATOR\"}]','[\"IN_APP\",\"EMAIL\"]','email_cutoff_si_48h',1,'GLOBAL','HIGH',NOW(),NOW()),
('INVOICE_OVERDUE_7D','Invoice Overdue 7 Days','FINANCIAL','{\"event\":\"INVOICE_OVERDUE\",\"days_overdue\":7}','[{\"type\":\"JOB_OPERATOR\"}]','[\"IN_APP\",\"EMAIL\"]','email_invoice_overdue',1,'GLOBAL','HIGH',NOW(),NOW())
");
        $this->addSql("INSERT INTO notification_template (key_col, name, channel, subject_template, body_template, language) VALUES
('email_milestone_vessel_departed','Vessel Departed Email','EMAIL','Vessel Departed — {{ shipment_code }}','<p>Shipment <strong>{{ shipment_code }}</strong> milestone: Vessel Departed on {{ actual_date }}.</p>','en'),
('email_milestone_vessel_arrived','Vessel Arrived Email','EMAIL','Vessel Arrived — {{ shipment_code }}','<p>Shipment <strong>{{ shipment_code }}</strong>: Vessel has arrived. Date: {{ actual_date }}.</p>','en'),
('email_milestone_delivered','Cargo Delivered Email','EMAIL','Cargo Delivered — {{ shipment_code }}','<p>Shipment <strong>{{ shipment_code }}</strong> has been delivered on {{ actual_date }}.</p>','en'),
('email_cutoff_si_48h','SI Cutoff Alert Email','EMAIL','SI Cutoff in {{ hours_remaining }}h — {{ shipment_code }}','<p>The SI cutoff for shipment <strong>{{ shipment_code }}</strong> is at {{ cutoff_si }}. Please submit the Shipping Instruction immediately.</p>','en'),
('email_invoice_overdue','Invoice Overdue Email','EMAIL','Invoice Overdue {{ days_overdue }} days — {{ invoice_code }}','<p>Invoice <strong>{{ invoice_code }}</strong> for shipment {{ shipment_code }} is {{ days_overdue }} days overdue. Please follow up with the client.</p>','en')
");
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException();
    }
}
