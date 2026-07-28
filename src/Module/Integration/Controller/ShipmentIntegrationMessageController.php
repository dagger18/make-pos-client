<?php
declare(strict_types=1);
namespace App\Module\Integration\Controller;

use App\Misc\Attribute\AppModule;
use App\Module\Integration\Entity\IntegrationMessage;
use App\Module\Integration\Repository\IntegrationMessageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/shipment/{shipmentId}/integration-messages')]
#[IsGranted('ROLE_USER')]
#[AppModule('integration')]
class ShipmentIntegrationMessageController extends AbstractController
{
    public function __construct(
        private readonly IntegrationMessageRepository $repo,
    ) {}

    #[Route('', methods: ['GET'])]
    public function list(int $shipmentId, Request $request): JsonResponse
    {
        $messages = $this->repo->findFiltered(
            direction:   $request->query->get('direction') ?: null,
            messageType: $request->query->get('message_type') ?: null,
            status:      $request->query->get('status') ?: null,
            partnerType: null,
            shipmentId:  $shipmentId,
            from:        null,
            to:          null,
            limit:       100,
            offset:      0,
        );

        return $this->json(array_map(fn($m) => $this->serialize($m), $messages));
    }

    private function serialize(IntegrationMessage $m): array
    {
        return [
            'id'          => $m->getId(),
            'direction'   => $m->getDirection(),
            'protocol'    => $m->getProtocol(),
            'messageType' => $m->getMessageType(),
            'partnerType' => $m->getPartnerType(),
            'partnerName' => $m->getPartnerName(),
            'shipmentId'  => $m->getShipment()?->getId(),
            'messageRef'  => $m->getMessageRef(),
            'status'      => $m->getStatus(),
            'retryCount'  => $m->getRetryCount(),
            'sentAt'      => $m->getSentAt()?->format(\DateTimeInterface::ATOM),
            'receivedAt'  => $m->getReceivedAt()?->format(\DateTimeInterface::ATOM),
            'createdAt'   => $m->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }
}
