<?php
declare(strict_types=1);
namespace SqlEngineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260622030000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Data: populate user_branch and user_department from existing user branch_id/department_id';
    }

    public function up(Schema $schema): void
    {
        $cols = array_column(
            $this->connection->executeQuery('PRAGMA table_info("user")')->fetchAllAssociative(),
            'name'
        );
        if (in_array('branch_id', $cols, true)) {
            $this->addSql('INSERT INTO user_branch (user_id, branch_id) SELECT id, branch_id FROM "user" WHERE branch_id IS NOT NULL');
        }
        if (in_array('department_id', $cols, true)) {
            $this->addSql('INSERT INTO user_department (user_id, department_id) SELECT id, department_id FROM "user" WHERE department_id IS NOT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException();
    }
}
