<?php

namespace App\Module\Core\Repository;

use App\Module\Core\Repository\BaseRepository;

class ComponentRepository extends BaseRepository
{
    public function getRowComponents($pageId, $rowNumber) {
        $queryBuilder = $this->createQueryBuilder('Component');
        $queryBuilder
            ->where($queryBuilder->expr()->eq('Component.page', ':page'))
            ->andWhere($queryBuilder->expr()->eq('Component.rowNumber', ':rowNumber'))
            ->setParameter('page', $pageId)
            ->setParameter('rowNumber', $rowNumber)
            ->addOrderBy('Component.orderNumber', 'ASC')
            ;
        return $queryBuilder->getQuery()->getResult();
    }
    public function getPageComponents($pageId) {
        $queryBuilder = $this->createQueryBuilder('Component');
        $queryBuilder
            ->where($queryBuilder->expr()->eq('Component.page', ':page'))
            ->setParameter('page', $pageId)
            ;
        return $queryBuilder->getQuery()->getResult();
        $rowMap = [];
        forEach($result as $component) {
            if(!isset($rowMap[$component->getRowNumber()])) {
                $rowMap[$component->getRowNumber()] = [];
            }
            $rowMap[$component->getRowNumber()][] = $component->getId();
        }
        ksort($rowMap);
        $rowMap = array_values($rowMap);
        forEach($rowMap as $index => $components) {
            forEach($components as $component) {
                $component->setRowNumber(($index+1)*100);
                $this->save($component);
            }
        }
    }
}