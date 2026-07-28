<?php
namespace App\Module\Carrier\Repository;

use App\Module\Core\Repository\BaseRepository;

use App\Module\Carrier\Entity\TrackingRequest;

class TrackingRequestRepository extends BaseRepository
{
    /** @return TrackingRequest[] */
    public function findByShipment(int $shipmentId): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.shipment = :sid')
            ->setParameter('sid', $shipmentId)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return TrackingRequest[]
     */
    public function findAllFiltered(?string $status, ?string $type): array
    {
        $qb = $this->createQueryBuilder('tr')
            ->addSelect('s')
            ->join('tr.shipment', 's')
            ->orderBy('tr.lastEventAt', 'DESC')
            ->addOrderBy('tr.id', 'DESC');

        if ($status !== null && $status !== '') {
            $qb->andWhere('tr.status = :status')->setParameter('status', $status);
        }
        if ($type !== null && $type !== '') {
            $qb->andWhere('tr.trackingType = :type')->setParameter('type', $type);
        }

        return $qb->getQuery()->getResult();
    }

    public function save($entity, ?\Symfony\Component\HttpFoundation\Request $request = null)
    {
        $em = $this->getEntityManager();
        $em->persist($entity);
        $em->flush();
        return $entity;
    }

    public function delete($entity, $inTransaction = false): void
    {
        $em = $this->getEntityManager();
        $em->remove($entity);
        $em->flush();
    }
}
