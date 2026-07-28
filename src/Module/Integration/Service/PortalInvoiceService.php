<?php
namespace App\Module\Integration\Service;

use App\Module\Crm\Entity\Client;
use App\Module\Operations\Entity\ShipmentParty;
use App\Module\Finance\Enum\EbitNoteType;
use App\Module\Finance\Repository\EbitNoteRepository;

class PortalInvoiceService
{
    public function __construct(
        private readonly EbitNoteRepository $ebitNoteRepository,
    ) {}

    public function getInvoicesForClient(Client $client): array
    {
        return $this->ebitNoteRepository->createQueryBuilder('e')
            ->innerJoin('e.shipment', 's')
            ->innerJoin(ShipmentParty::class, 'p', 'WITH', 'p.shipment = s AND p.client = :client')
            ->where('e.type = :type')
            ->setParameter('client', $client)
            ->setParameter('type', EbitNoteType::InvoiceDebit)
            ->orderBy('e.createdDate', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
