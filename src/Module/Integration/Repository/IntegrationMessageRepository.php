<?php
namespace App\Module\Integration\Repository;

use App\Module\Core\Repository\BaseRepository;
use App\Module\Integration\Entity\IntegrationMessage;

class IntegrationMessageRepository extends BaseRepository
{
    public function findFiltered(
        ?string $direction,
        ?string $messageType,
        ?string $status,
        ?string $partnerType,
        ?int    $shipmentId,
        ?string $from,
        ?string $to,
        int     $limit  = 50,
        int     $offset = 0,
    ): array {
        $qb = $this->createQueryBuilder('m')
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        if ($direction)   { $qb->andWhere('m.direction = :dir')->setParameter('dir', $direction); }
        if ($messageType) { $qb->andWhere('m.messageType = :mt')->setParameter('mt', $messageType); }
        if ($status)      { $qb->andWhere('m.status = :st')->setParameter('st', $status); }
        if ($partnerType) { $qb->andWhere('m.partnerType = :pt')->setParameter('pt', $partnerType); }
        if ($shipmentId)  { $qb->andWhere('m.shipment = :sid')->setParameter('sid', $shipmentId); }
        if ($from)        { $qb->andWhere('m.createdAt >= :from')->setParameter('from', new \DateTime($from)); }
        if ($to)          { $qb->andWhere('m.createdAt <= :to')->setParameter('to', new \DateTime($to . ' 23:59:59')); }

        return $qb->getQuery()->getResult();
    }
}
