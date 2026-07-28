<?php
declare(strict_types=1);

namespace App\Misc\Doctrine;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\Migrations\Version\MigrationFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

class MigrationFactoryDecorator implements MigrationFactory
{

    public function __construct(
        private MigrationFactory $migrationFactory, 
        private ContainerInterface $container
    ){}

    public function createVersion(string $migrationClassName): AbstractMigration
    {
        $instance = $this->migrationFactory->createVersion($migrationClassName);

        set_error_handler(static fn() => true, E_DEPRECATED);
        /** @disregard P1014 */ $instance->container = $this->container;
        /** @disregard P1014 */ $instance->conn = $this->container->get('doctrine.orm.entity_manager')->getConnection();
        restore_error_handler();

        return $instance;
    }
}