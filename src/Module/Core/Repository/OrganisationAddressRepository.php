<?php
namespace App\Module\Core\Repository;

use App\Module\Core\Entity\OrganisationAddress;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class OrganisationAddressRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrganisationAddress::class);
    }

    public function findByClient(int $clientId): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.client = :clientId')
            ->setParameter('clientId', $clientId)
            ->orderBy('a.addressType', 'ASC')
            ->addOrderBy('a.isDefault', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByProvider(int $providerId): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.provider = :providerId')
            ->setParameter('providerId', $providerId)
            ->orderBy('a.addressType', 'ASC')
            ->addOrderBy('a.isDefault', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function save(OrganisationAddress $entity): OrganisationAddress
    {
        $this->getEntityManager()->persist($entity);
        $this->getEntityManager()->flush();
        return $entity;
    }
}
