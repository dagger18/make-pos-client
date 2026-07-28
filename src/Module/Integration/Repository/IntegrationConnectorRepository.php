<?php
namespace App\Module\Integration\Repository;

use App\Module\Core\Repository\BaseRepository;
use App\Module\Integration\Entity\IntegrationConnector;

class IntegrationConnectorRepository extends BaseRepository
{
    public function findFiltered(?string $connectorType, ?bool $isActive): array
    {
        $qb = $this->createQueryBuilder('c')->orderBy('c.partnerName', 'ASC');
        if ($connectorType !== null) {
            $qb->andWhere('c.connectorType = :ct')->setParameter('ct', $connectorType);
        }
        if ($isActive !== null) {
            $qb->andWhere('c.isActive = :active')->setParameter('active', $isActive);
        }
        return $qb->getQuery()->getResult();
    }
}
