<?php

namespace App\Module\Carrier\Repository;

use App\Module\Core\Repository\BaseRepository;

class ProviderRepository extends BaseRepository
{
    public function getList($requestData = [], $group = null, callable $extraFilter = null, $returnCount = false)
    {
        $transportType = $requestData['filter_transportTypes'] ?? null;
        unset($requestData['filter_transportTypes']);

        $combined = function ($qb) use ($extraFilter, $transportType) {
            if ($extraFilter) {
                $extraFilter($qb);
            }
            if ($transportType) {
                $alias = $this->getEntityName();
                $qb->andWhere(
                    $qb->expr()->orX(
                        $qb->expr()->isNull("$alias.transportTypes"),
                        $qb->expr()->eq("$alias.transportTypes", ':tt'),
                        $qb->expr()->like("$alias.transportTypes", ':ttStart'),
                        $qb->expr()->like("$alias.transportTypes", ':ttMiddle'),
                        $qb->expr()->like("$alias.transportTypes", ':ttEnd')
                    )
                )
                ->setParameter('tt',       $transportType)
                ->setParameter('ttStart',  $transportType . ',%')
                ->setParameter('ttMiddle', '%,' . $transportType . ',%')
                ->setParameter('ttEnd',    '%,' . $transportType);
            }
        };

        return parent::getList($requestData, $group, $combined, $returnCount);
    }

    public function findPotentialDuplicates(string $name, ?string $taxNumber = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->select('p.id, p.name, p.code, p.taxNumber, p.country')
            ->where('LOWER(p.name) LIKE LOWER(:name)')
            ->setParameter('name', '%' . $name . '%')
            ->setMaxResults(5);

        if ($taxNumber) {
            $qb->orWhere('p.taxNumber = :taxNumber')
               ->setParameter('taxNumber', $taxNumber);
        }

        return $qb->getQuery()->getArrayResult();
    }
}
