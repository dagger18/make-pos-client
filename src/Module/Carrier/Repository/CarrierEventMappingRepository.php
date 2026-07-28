<?php
namespace App\Module\Carrier\Repository;

use App\Module\Core\Repository\BaseRepository;

use App\Module\Carrier\Entity\CarrierEventMapping;

class CarrierEventMappingRepository extends BaseRepository
{
    public function findByCarrierAndCode(string $carrierScac, string $eventCode): ?CarrierEventMapping
    {
        return $this->findOneBy([
            'carrierScac'      => $carrierScac,
            'carrierEventCode' => $eventCode,
        ]);
    }
}
