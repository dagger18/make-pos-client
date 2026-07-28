<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'organization:migrate-from-sqlite',
    description: 'Copy a demo SQLite database into the current MySQL database.',
)]
class OrganizationMigrateFromSqliteCommand extends Command
{
    // Never overwrite MySQL's migration tracking with SQLite's version list
    private const SKIP_TABLES = ['doctrine_migration_versions'];

    public function __construct(
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('token', InputArgument::REQUIRED, 'Organization token (used for logging)')
            ->addArgument('sqlite-file', InputArgument::REQUIRED, 'Absolute path to the SQLite .db file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io         = new SymfonyStyle($input, $output);
        $token      = $input->getArgument('token');
        $sqlitePath = $input->getArgument('sqlite-file');

        if (!file_exists($sqlitePath)) {
            $io->error("SQLite file not found: {$sqlitePath}");
            return Command::FAILURE;
        }

        $sqlite = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'path'   => $sqlitePath,
        ]);

        $mysql = $this->connection;

        $sqliteTables = array_values(array_filter(
            $sqlite->createSchemaManager()->listTableNames(),
            fn(string $t) => !str_starts_with($t, 'sqlite_') && !in_array($t, self::SKIP_TABLES, true),
        ));

        $mysqlTableSet = array_flip($mysql->createSchemaManager()->listTableNames());

        $io->title("Migrating SQLite → MySQL for organization {$token}");

        $mysql->executeStatement('SET FOREIGN_KEY_CHECKS=0');

        $copied  = 0;
        $skipped = 0;

        try {
            foreach ($sqliteTables as $table) {
                if (!isset($mysqlTableSet[$table])) {
                    $io->writeln("  <comment>skip (not in MySQL):</comment> {$table}");
                    $skipped++;
                    continue;
                }

                $rows = $sqlite->fetchAllAssociative("SELECT * FROM `{$table}`");

                // Wipe seed data inserted by migrations so SQLite data takes precedence
                $mysql->executeStatement("DELETE FROM `{$table}`");

                if (empty($rows)) {
                    continue;
                }

                // Pre-build quoted column list once per table (handles reserved words like `row_number`)
                $quotedCols   = implode(', ', array_map(fn($c) => "`{$c}`", array_keys($rows[0])));
                $placeholders = implode(', ', array_fill(0, count($rows[0]), '?'));
                $sql          = "INSERT INTO `{$table}` ({$quotedCols}) VALUES ({$placeholders})";

                foreach ($rows as $row) {
                    $mysql->executeStatement($sql, array_values($row));
                }

                $io->writeln("  <info>✓</info> {$table} (" . count($rows) . ' rows)');
                $copied++;
            }
        } finally {
            // Always restore FK checks even if an insert fails mid-table
            $mysql->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        }

        $io->success("Done. {$copied} tables copied, {$skipped} skipped (token: {$token}).");

        return Command::SUCCESS;
    }
}
