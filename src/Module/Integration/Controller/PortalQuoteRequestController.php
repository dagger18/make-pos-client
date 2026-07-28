<?php
namespace App\Module\Integration\Controller;

use App\Module\Core\Entity\Port;

use App\Module\Integration\Repository\PortalQuoteRequestRepository;
use App\Module\Integration\Service\PortalQuoteRequestService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Misc\Attribute\AppModule;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/portal/quote-requests')]
#[IsGranted('ROLE_PORTAL_USER')]
#[AppModule('integration')]
class PortalQuoteRequestController extends AbstractController
{
    public function __construct(
        private readonly PortalQuoteRequestService    $service,
        private readonly PortalQuoteRequestRepository $repository,
    ) {}

    #[Route('', methods: ['GET'])]
    public function list(#[CurrentUser] $user): JsonResponse
    {
        /** @var PortalUser $user */
        $requests = $this->repository->findByPortalUser($user);

        return $this->json(array_map(fn($q) => [
            'id'             => $q->getId(),
            'transportMode'  => $q->getTransportMode(),
            'origin'         => $q->getOrigin(),
            'destination'    => $q->getDestination(),
            'status'         => $q->getStatus(),
            'cargoReadyDate' => $q->getCargoReadyDate()?->format('Y-m-d'),
            'createdAt'      => $q->getCreatedDate()?->format(\DateTimeInterface::ATOM),
        ], $requests));
    }

    #[Route('', methods: ['POST'])]
    public function create(#[CurrentUser] $user, Request $request): JsonResponse
    {
        /** @var PortalUser $user */
        $body = json_decode($request->getContent(), true) ?? [];
        $qr = $this->service->create($user, $body);

        return $this->json([
            'id'     => $qr->getId(),
            'status' => $qr->getStatus(),
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['GET'])]
    public function detail(#[CurrentUser] $user, int $id): JsonResponse
    {
        /** @var PortalUser $user */
        $qr = $this->repository->find($id);
        if (!$qr || $qr->getPortalUser()->getId() !== $user->getId()) {
            throw $this->createNotFoundException();
        }

        return $this->json([
            'id'                  => $qr->getId(),
            'transportMode'       => $qr->getTransportMode(),
            'serviceType'         => $qr->getServiceType(),
            'origin'              => $qr->getOrigin(),
            'destination'         => $qr->getDestination(),
            'cargoDescription'    => $qr->getCargoDescription(),
            'weightKg'            => $qr->getWeightKg(),
            'volumeCbm'           => $qr->getVolumeCbm(),
            'containerType'       => $qr->getContainerType(),
            'incoterm'            => $qr->getIncoterm(),
            'cargoReadyDate'      => $qr->getCargoReadyDate()?->format('Y-m-d'),
            'specialRequirements' => $qr->getSpecialRequirements(),
            'status'              => $qr->getStatus(),
            'createdAt'           => $qr->getCreatedDate()?->format(\DateTimeInterface::ATOM),
        ]);
    }
}
