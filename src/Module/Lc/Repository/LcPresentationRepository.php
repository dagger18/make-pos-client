<?php
declare(strict_types=1);
namespace App\Module\Lc\Repository;

use App\Module\Lc\Entity\LcPresentation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class LcPresentationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LcPresentation::class);
    }

    public function findByLc(int $lcId): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.lc = :lcId')
            ->setParameter('lcId', $lcId)
            ->orderBy('p.presentedAt', 'DESC')
            ->getQuery()->getResult();
    }
}
