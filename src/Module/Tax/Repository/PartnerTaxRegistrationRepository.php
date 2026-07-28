<?php
namespace App\Module\Tax\Repository;

use App\Module\Tax\Entity\PartnerTaxRegistration;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PartnerTaxRegistrationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PartnerTaxRegistration::class);
    }

    public function findForPartner(int $partnerId): array
    {
        return $this->findBy(['partner' => $partnerId], ['effectiveFrom' => 'DESC']);
    }

    public function save(PartnerTaxRegistration $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(PartnerTaxRegistration $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
