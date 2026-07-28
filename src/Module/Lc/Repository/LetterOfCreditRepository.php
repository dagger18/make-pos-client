<?php
declare(strict_types=1);
namespace App\Module\Lc\Repository;

use App\Module\Lc\Entity\LetterOfCredit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class LetterOfCreditRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LetterOfCredit::class);
    }

    public function findByShipment(int $shipmentId): array
    {
        return $this->createQueryBuilder('lc')
            ->andWhere('lc.shipment = :sid')
            ->setParameter('sid', $shipmentId)
            ->orderBy('lc.createdAt', 'DESC')
            ->getQuery()->getResult();
    }

    public function findOpen(): array
    {
        return $this->createQueryBuilder('lc')
            ->andWhere('lc.status NOT IN (:closed)')
            ->setParameter('closed', ['PAID', 'EXPIRED', 'CANCELLED'])
            ->orderBy('lc.shipmentBy', 'ASC')
            ->getQuery()->getResult();
    }

    public function search(array $params): array
    {
        $qb = $this->createQueryBuilder('lc');
        if (!empty($params['shipmentId'])) {
            $qb->andWhere('lc.shipment = :sid')->setParameter('sid', $params['shipmentId']);
        }
        if (!empty($params['status'])) {
            $qb->andWhere('lc.status = :status')->setParameter('status', $params['status']);
        }
        if (!empty($params['lcNumber'])) {
            $qb->andWhere('lc.lcNumber LIKE :num')->setParameter('num', '%'.$params['lcNumber'].'%');
        }
        return $qb->orderBy('lc.createdAt', 'DESC')->setMaxResults(200)->getQuery()->getResult();
    }
}
