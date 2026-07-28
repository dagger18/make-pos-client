<?php
declare(strict_types=1);
namespace SqlEngineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260626150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Data: seed default data retention policies for freight forwarding compliance';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("INSERT INTO data_retention_policy
            (data_category, retention_years, legal_basis, applies_to, auto_delete, created_at) VALUES
            ('FINANCIAL',     10, 'Tax law — invoices, payments, and financial records must be retained for tax audit purposes', 'invoices, payments, credit_notes, ebit_notes', 0, NOW()),
            ('CUSTOMS',        7, 'Customs law — entry records required for post-clearance audit', 'customs_entries, hs_codes on shipments', 0, NOW()),
            ('JOB_DATA',       7, 'Contract and liability law — operational records required for dispute resolution', 'shipment, booking, instruction, consignment', 0, NOW()),
            ('DOCUMENTS',      7, 'Shipping law — bills of lading and transport documents', 'media attached to shipments', 0, NOW()),
            ('PERSONAL',       3, 'GDPR Article 5(1)(e) — personal data not retained longer than necessary', 'contact: firstName, lastName, email, phone, mobile', 0, NOW()),
            ('AUDIT_LOG',      7, 'Regulatory compliance — audit trail required for regulatory inspection', 'system_audit_log', 0, NOW()),
            ('MARKETING',      2, 'GDPR Article 6 — consent-based marketing data', 'newsletter subscriptions, marketing lists', 0, NOW())
        ");
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException();
    }
}
