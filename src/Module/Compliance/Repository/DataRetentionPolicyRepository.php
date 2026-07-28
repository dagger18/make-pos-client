<?php
declare(strict_types=1);
namespace App\Module\Compliance\Repository;

use App\Module\Compliance\Entity\DataRetentionPolicy;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class DataRetentionPolicyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DataRetentionPolicy::class);
    }

    public function save(DataRetentionPolicy $p): void
    {
        $this->getEntityManager()->persist($p);
        $this->getEntityManager()->flush();
    }

    public function remove(DataRetentionPolicy $p): void
    {
        $this->getEntityManager()->remove($p);
        $this->getEntityManager()->flush();
    }
}
