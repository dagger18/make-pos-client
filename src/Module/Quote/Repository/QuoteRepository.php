<?php

namespace App\Module\Quote\Repository;

use App\Module\Core\Repository\BaseRepository;

class QuoteRepository extends BaseRepository
{
    public function getThisMonthCounter() {
        $from = (new \DateTime('first day of this month'))->format('Y-m-01 00:00:00');
        $to = (new \DateTime('last day of this month'))->format('Y-m-d 23:59:59');

        $queryBuilder = $this->createQueryBuilder('Quote');
        $queryBuilder
            ->where('Quote.createdDate BETWEEN :from AND :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->addOrderBy('Quote.id', 'DESC')
            ->setMaxResults(1)
        ;
        $lastQuote = $queryBuilder->getQuery()->getOneOrNullResult();
        if(is_null($lastQuote)) {
            $lastNum = 0;
        } else {
            preg_match('/[A-Z]+_([0-9]+)_([0-9]+)/m', $lastQuote->getCode(), $matches);
            $lastNum = (int) $matches[2];
        }
        return str_pad($lastNum + 1, 3, '0', STR_PAD_LEFT);
    }
}
