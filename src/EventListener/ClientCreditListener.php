<?php
// src/EventListener/ClientCreditListener.php
namespace App\EventListener;

use App\Module\Crm\Entity\Client;
use App\Module\Finance\Enum\CreditStatus;
use App\Module\Operations\Repository\ShipmentRepository;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::postUpdate, entity: Client::class)]
class ClientCreditListener
{
    public function __construct(
        private readonly ShipmentRepository     $shipmentRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    public function postUpdate(Client $client, PostUpdateEventArgs $args): void
    {
        $changeSet = $args->getObjectManager()->getUnitOfWork()->getEntityChangeSet($client);

        if (!isset($changeSet['creditStatus'])) {
            return;
        }

        [, $newStatus] = $changeSet['creditStatus'];

        if (is_string($newStatus)) {
            $newStatus = CreditStatus::from($newStatus);
        }

        $holdStatuses = [CreditStatus::OnHold, CreditStatus::Blocked, CreditStatus::Blacklisted];

        if (in_array($newStatus, $holdStatuses)) {
            $this->flagShipmentsOnHold($client, $newStatus);
        } elseif ($newStatus === CreditStatus::Active) {
            $this->clearCreditHoldOnShipments($client);
        }
    }

    private function flagShipmentsOnHold(Client $client, CreditStatus $status): void
    {
        $reason = 'CREDIT_HOLD: Client credit status changed to ' . $status->value;
        $shipments = $this->shipmentRepository->findActiveByClient($client->getId());
        foreach ($shipments as $shipment) {
            $shipment->noLog = true;
            $shipment->setIsOnHold(true);
            $shipment->setHoldReason($reason);
        }
        if (!empty($shipments)) {
            $this->em->flush();
        }
    }

    private function clearCreditHoldOnShipments(Client $client): void
    {
        $shipments = $this->shipmentRepository->findActiveByClient($client->getId());
        foreach ($shipments as $shipment) {
            if (str_starts_with((string) $shipment->getHoldReason(), 'CREDIT_HOLD:')) {
                $shipment->noLog = true;
                $shipment->setIsOnHold(false);
                $shipment->setHoldReason(null);
            }
        }
        if (!empty($shipments)) {
            $this->em->flush();
        }
    }
}
