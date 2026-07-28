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
        $sm = $this->connection->createSchemaManager();
        $userCols = array_map(
            static fn($c) => $c->getName(),
            $sm->listTableColumns('user')
        );
        if (in_array('branch_id', $userCols, true)) {
            $this->addSql('INSERT INTO user_branch (user_id, branch_id) SELECT id, branch_id FROM `user` WHERE branch_id IS NOT NULL');
        }
        if (in_array('department_id', $userCols, true)) {
            $this->addSql('INSERT INTO user_department (user_id, department_id) SELECT id, department_id FROM `user` WHERE department_id IS NOT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException();
    }
}
