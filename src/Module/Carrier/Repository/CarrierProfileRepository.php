<?php
namespace App\Module\Carrier\Repository;

use App\Module\Carrier\Entity\CarrierProfile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CarrierProfileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CarrierProfile::class);
    }

    public function save(CarrierProfile $entity): CarrierProfile
    {
        $this->getEntityManager()->persist($entity);
        $this->getEntityManager()->flush();
        return $entity;
    }
}
