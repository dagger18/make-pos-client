<?php

namespace App\Module\Operations\Repository;

use App\Module\Operations\Entity\Consolidation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ConsolidationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Consolidation::class);
    }

    public function generateCode(string $transportMode): string
    {
        $ym = (new \DateTime())->format('Ym');
        $prefix = "CONSOL-{$transportMode}-{$ym}-";

        $count = (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.code LIKE :prefix')
            ->setParameter('prefix', $prefix . '%')
            ->getQuery()
            ->getSingleScalarResult();

        return $prefix . str_pad((string) ($count + 1), 3, '0', STR_PAD_LEFT);
    }

    public function findByFilters(array $filters = []): array
    {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.pol',     'pol')->addSelect('pol')
            ->leftJoin('c.pod',     'pod')->addSelect('pod')
            ->leftJoin('c.carrier', 'car')->addSelect('car')
            ->orderBy('c.createdAt', 'DESC');

        if (!empty($filters['status'])) {
            $qb->andWhere('c.status = :status')->setParameter('status', $filters['status']);
        }
        if (!empty($filters['transportMode'])) {
            $qb->andWhere('c.transportMode = :mode')->setParameter('mode', $filters['transportMode']);
        }

        return $qb->getQuery()->getResult();
    }

    public function save(Consolidation $consol): void
    {
        $em = $this->getEntityManager();
        $em->persist($consol);
        $em->flush();
    }

    public function delete(Consolidation $consol): void
    {
        $em = $this->getEntityManager();
        $em->remove($consol);
        $em->flush();
    }
}
