<?php
declare(strict_types=1);
namespace App\Module\Integration\Controller;

use App\Misc\Attribute\AppModule;
use App\Module\Integration\Entity\IntegrationMessage;
use App\Module\Integration\Repository\IntegrationMessageRepository;
use App\Module\Operations\Repository\ShipmentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/integration/messages')]
#[IsGranted('ROLE_USER')]
#[AppModule('integration')]
class IntegrationMessageController extends AbstractController
{
    public function __construct(
        private readonly IntegrationMessageRepository $repo,
        private readonly ShipmentRepository           $shipmentRepository,
    ) {}

    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $messages = $this->repo->findFiltered(
            direction:   $request->query->get('direction') ?: null,
            messageType: $request->query->get('message_type') ?: null,
            status:      $request->query->get('status') ?: null,
            partnerType: $request->query->get('partner_type') ?: null,
            shipmentId:  $request->query->getInt('shipment_id') ?: null,
            from:        $request->query->get('from') ?: null,
            to:          $request->query->get('to') ?: null,
            limit:       min(200, max(1, (int) ($request->query->get('limit', 50)))),
            offset:      max(0, (int) ($request->query->get('offset', 0))),
        );

        return $this->json(array_map(fn($m) => $this->serializeList($m), $messages));
    }

    #[Route('/{id}', methods: ['GET'])]
    public function detail(int $id): JsonResponse
    {
        $message = $this->repo->find($id);
        if (!$message) {
            throw $this->createNotFoundException();
        }

        return $this->json($this->serializeDetail($message));
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $body = json_decode($request->getContent(), true) ?? [];

        $required = ['direction', 'protocol', 'messageType', 'partnerType', 'payload'];
        foreach ($required as $field) {
            if (empty($body[$field])) {
                return $this->json(['error' => $this->trans("$field is required.")], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        $message = new IntegrationMessage();
        $message->setDirection($body['direction']);
        $message->setProtocol($body['protocol']);
        $message->setMessageType($body['messageType']);
        $message->setPartnerType($body['partnerType']);
        $message->setPayload($body['payload']);

        if (!empty($body['partnerName'])) { $message->setPartnerName($body['partnerName']); }
        if (!empty($body['messageRef']))  { $message->setMessageRef($body['messageRef']); }
        if (!empty($body['status']))      { $message->setStatus($body['status']); }
        if (!empty($body['errorCode']))   { $message->setErrorCode($body['errorCode']); }
        if (!empty($body['errorMessage'])){ $message->setErrorMessage($body['errorMessage']); }
        if (isset($body['retryCount']))   { $message->setRetryCount((int) $body['retryCount']); }

        if (!empty($body['shipmentId'])) {
            $shipment = $this->shipmentRepository->find((int) $body['shipmentId']);
            if ($shipment) { $message->setShipment($shipment); }
        }

        foreach (['sentAt', 'receivedAt', 'ackAt'] as $f) {
            if (!empty($body[$f])) {
                $setter = 'set' . ucfirst($f);
                $message->$setter(new \DateTime($body[$f]));
            }
        }

        $this->repo->save($message, true);

        return $this->json($this->serializeDetail($message), Response::HTTP_CREATED);
    }

    private function serializeList(IntegrationMessage $m): array
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

    private function serializeDetail(IntegrationMessage $m): array
    {
        return [
            ...$this->serializeList($m),
            'payload'      => $m->getPayload(),
            'ackAt'        => $m->getAckAt()?->format(\DateTimeInterface::ATOM),
            'errorCode'    => $m->getErrorCode(),
            'errorMessage' => $m->getErrorMessage(),
        ];
    }
}
