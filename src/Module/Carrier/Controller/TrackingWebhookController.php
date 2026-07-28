<?php
namespace App\Module\Carrier\Controller;

use App\Module\Carrier\Entity\TrackingEventRaw;
use App\Module\Carrier\Repository\TrackingEventRawRepository;
use App\Module\Carrier\Repository\TrackingRequestRepository;
use App\Module\Carrier\Service\TrackingMilestoneWriterService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Misc\Attribute\AppModule;
use Symfony\Component\Routing\Annotation\Route;

#[AppModule('carrier')]
class TrackingWebhookController extends AbstractController
{
    public function __construct(
        private readonly TrackingRequestRepository      $requestRepository,
        private readonly TrackingEventRawRepository     $eventRepository,
        private readonly TrackingMilestoneWriterService $milestoneWriter,
    ) {}

    #[Route('/tracking-webhook/{trackingRequestId}', methods: ['POST'])]
    public function receive(int $trackingRequestId, Request $request): JsonResponse
    {
        $trackingRequest = $this->requestRepository->find($trackingRequestId);
        if (!$trackingRequest) {
            return $this->json(['error' => $this->trans('Not found.')], Response::HTTP_NOT_FOUND);
        }

        $secret = $request->headers->get('X-Tracking-Secret', '');
        if (!hash_equals($trackingRequest->getWebhookSecret(), $secret)) {
            return $this->json(['error' => $this->trans('Invalid secret.')], Response::HTTP_FORBIDDEN);
        }

        $body = json_decode($request->getContent(), true) ?? [];
        $source = $body['source'] ?? 'UNKNOWN';
        $events = $body['events'] ?? [];

        $raw = new TrackingEventRaw();
        $raw->setTrackingRequest($trackingRequest);
        $raw->setSource($source);
        $raw->setRawPayload($body);

        try {
            $this->milestoneWriter->writeEvents($trackingRequest, $source, $events);
            $raw->setIsProcessed(true);
            $raw->setProcessedAt(new \DateTime());

            $trackingRequest->setLastCheckedAt(new \DateTime());
            if (!empty($events)) {
                $trackingRequest->setLastEventAt(new \DateTime());
            }
            $this->requestRepository->save($trackingRequest);
        } catch (\Throwable $e) {
            $raw->setError($e->getMessage());
        }

        $this->eventRepository->save($raw);

        return $this->json(['received' => true]);
    }
}
