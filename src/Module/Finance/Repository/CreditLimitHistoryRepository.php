<?php
namespace App\Module\Finance\Repository;

use App\Module\Core\Repository\BaseRepository;

use App\Module\Finance\Entity\CreditLimitHistory;
use Symfony\Component\HttpFoundation\Request;

class CreditLimitHistoryRepository extends BaseRepository
{
    public function findForClient(int $clientId): array
    {
        return $this->createQueryBuilder('h')
            ->where('h.client = :clientId')
            ->setParameter('clientId', $clientId)
            ->leftJoin('h.changedBy', 'u')
            ->addSelect('u')
            ->orderBy('h.createdDate', 'DESC')
            ->setMaxResults(100)
            ->getQuery()
            ->getResult();
    }

    public function save($entity, ?Request $request = null)
    {
        $this->getEntityManager()->persist($entity);
        $this->getEntityManager()->flush();
        return $entity;
    }
}
