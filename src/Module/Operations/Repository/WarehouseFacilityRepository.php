<?php

namespace App\Module\Operations\Repository;

use App\Module\Operations\Entity\WarehouseFacility;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class WarehouseFacilityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WarehouseFacility::class);
    }

    public function save(WarehouseFacility $facility): WarehouseFacility
    {
        $this->getEntityManager()->persist($facility);
        $this->getEntityManager()->flush();
        return $facility;
    }

    public function delete(WarehouseFacility $facility): void
    {
        $this->getEntityManager()->remove($facility);
        $this->getEntityManager()->flush();
    }

    /** @return WarehouseFacility[] */
    public function findActive(): array
    {
        return $this->findBy(['isActive' => true], ['name' => 'ASC']);
    }
}
