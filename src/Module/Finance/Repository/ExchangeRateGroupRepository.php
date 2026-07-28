<?php

namespace App\Module\Finance\Repository;

use App\Module\Core\Repository\BaseRepository;

class ExchangeRateGroupRepository extends BaseRepository
{
    public function getCurrentGroup() {
        $queryBuilder = $this->createQueryBuilder('G');
        $queryBuilder
        ->addOrderBy('G.id', 'DESC')
        ->setMaxResults(1);
        return $queryBuilder->getQuery()->getOneOrNullResult();
    }
}