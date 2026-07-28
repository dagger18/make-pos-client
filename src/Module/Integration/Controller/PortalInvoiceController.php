<?php
namespace App\Module\Integration\Controller;

use App\Module\Core\Entity\Port;

use App\Module\Integration\Service\PortalInvoiceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Misc\Attribute\AppModule;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/portal/invoices')]
#[IsGranted('ROLE_PORTAL_USER')]
#[AppModule('integration')]
class PortalInvoiceController extends AbstractController
{
    public function __construct(
        private readonly PortalInvoiceService $service,
    ) {}

    #[Route('', methods: ['GET'])]
    public function list(#[CurrentUser] $user): JsonResponse
    {
        /** @var PortalUser $user */
        $invoices = $this->service->getInvoicesForClient($user->getClient());

        return $this->json(array_map(fn($e) => [
            'id'           => $e->getId(),
            'code'         => $e->getCode(),
            'shipmentId'   => $e->getShipment()?->getId(),
            'shipmentCode' => $e->getShipment()?->getCode(),
            'amount'       => $e->getAmount()?->getAmount(),
            'currency'     => $e->getAmount()?->getCurrency(),
            'status'       => $e->getStatus()?->value,
            'createdAt'    => $e->getCreatedDate()?->format(\DateTimeInterface::ATOM),
        ], $invoices));
    }
}
