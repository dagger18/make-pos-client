<?php
namespace App\Module\Integration\Service;

use App\Module\Integration\Entity\PortalQuoteRequest;
use App\Module\Integration\Entity\PortalUser;
use App\Module\Integration\Repository\PortalQuoteRequestRepository;

class PortalQuoteRequestService
{
    public function __construct(
        private readonly PortalQuoteRequestRepository $repository,
    ) {}

    public function create(PortalUser $user, array $body): PortalQuoteRequest
    {
        $qr = new PortalQuoteRequest();
        $qr->setPortalUser($user);
        $qr->setTransportMode($body['transportMode'] ?? null);
        $qr->setServiceType($body['serviceType'] ?? null);
        $qr->setOrigin($body['origin'] ?? null);
        $qr->setDestination($body['destination'] ?? null);
        $qr->setCargoDescription($body['cargoDescription'] ?? null);
        $qr->setWeightKg(isset($body['weightKg']) ? (string) $body['weightKg'] : null);
        $qr->setVolumeCbm(isset($body['volumeCbm']) ? (string) $body['volumeCbm'] : null);
        $qr->setContainerType($body['containerType'] ?? null);
        $qr->setIncoterm($body['incoterm'] ?? null);
        $qr->setSpecialRequirements($body['specialRequirements'] ?? null);
        if (!empty($body['cargoReadyDate'])) {
            $qr->setCargoReadyDate(new \DateTime($body['cargoReadyDate']));
        }
        return $this->repository->save($qr);
    }
}
