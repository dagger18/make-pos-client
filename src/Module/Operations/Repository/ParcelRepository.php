<?php
namespace App\Module\Operations\Repository;

use App\Module\Core\Repository\BaseRepository;

class ParcelRepository extends BaseRepository
{
    public function findByShipment(int $shipmentId): array
    {
        return $this->findBy(['shipment' => $shipmentId], ['id' => 'ASC']);
    }
}
