<?php

namespace App\Module\Crm\Repository;

use App\Module\Core\Repository\BaseRepository;

class ClientRepository extends BaseRepository
{
    public function findPotentialDuplicates(string $name, ?string $taxNumber = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->select('c.id, c.name, c.code, c.taxNumber, c.country')
            ->where('LOWER(c.name) LIKE LOWER(:name)')
            ->setParameter('name', '%' . $name . '%')
            ->setMaxResults(5);

        if ($taxNumber) {
            $qb->orWhere('c.taxNumber = :taxNumber')
               ->setParameter('taxNumber', $taxNumber);
        }

        return $qb->getQuery()->getArrayResult();
    }
}