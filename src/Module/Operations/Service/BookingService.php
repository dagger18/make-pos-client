<?php
namespace App\Module\Operations\Service;

use App\Module\Core\Service\BaseService;

use App\Module\Quote\Entity\Quote;
use App\Module\Operations\Repository\BookingRepository;

class BookingService extends BaseService
{
    public function __construct(
        protected BaseService $baseService,
        public BookingRepository $repository,
    ) {
        $this->reflectFromParent($baseService);
    }

    public function reflectToBookingOnUpdateQuote(Quote $entity, array $changeSet) {
        if(isset($changeSet['originDoor']) 
            || isset($changeSet['destinationDoor'])
            || isset($changeSet['client'])
            || isset($changeSet['estimatedDeparture'])
            || isset($changeSet['cargoVolume'])
            || isset($changeSet['commodities'])
        ) {
            $booking = $entity->getShipment()->getBooking();
            if(isset($changeSet['originDoor'])) {
                $booking->setPortLoading($entity->getOriginPort());
                $booking->setPlaceReceipt($entity->getOriginDoor());
            }
            if(isset($changeSet['destinationDoor'])) {
                $booking->setPortDischarge($entity->getDestinationPort());
                $booking->setPlaceDelivery($entity->getDestinationDoor());
                $booking->setDestination($entity->getDestinationDoor());
            }
            if(isset($changeSet['client'])) {
                $booking->setBookingTo($entity->getClient()->getName());
            }
            if(isset($changeSet['vesselNo'])) {
                $booking->setVesselNo($entity->getVesselNo());
            }
            if(isset($changeSet['estimatedDeparture'])) {
                $booking->setEtd($entity->getEstimatedDeparture());
            }
            if(isset($changeSet['cargoVolume'])) {
                $booking->setCargoVolume($entity->getCargoVolume());
            }
            if(isset($changeSet['commodities'])) {
                $booking->setCommodities($entity->getCommodities());
            }
            $this->repository->save($booking);
        }
    }
}
