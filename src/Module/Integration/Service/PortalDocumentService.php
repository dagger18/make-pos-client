<?php
namespace App\Module\Integration\Service;

use App\Module\Crm\Entity\Client;
use App\Module\Operations\Entity\ShipmentParty;
use App\Module\Operations\Repository\ShipmentDocumentRepository;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class PortalDocumentService
{
    public function __construct(
        private readonly ShipmentDocumentRepository $documentRepository,
        private readonly ParameterBagInterface      $params,
    ) {}

    public function getDocumentsForClient(Client $client): array
    {
        return $this->documentRepository->createQueryBuilder('d')
            ->innerJoin('d.shipment', 's')
            ->innerJoin(ShipmentParty::class, 'p', 'WITH', 'p.shipment = s AND p.client = :client')
            ->where('d.isCustomerAccessible = true')
            ->setParameter('client', $client)
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function generateDownloadUrl(int $documentId): string
    {
        $expires = time() + 900; // 15 minutes
        $sig     = hash_hmac('sha256', "portal_doc:{$documentId}:{$expires}", $this->getSecret());
        return $this->getDomain() . "/portal/documents/{$documentId}/file?expires={$expires}&sig={$sig}";
    }

    private function getDomain(): string
    {
        $appBaseUrl = $this->params->get('app_base_url');
        if (!empty($appBaseUrl)) {
            return rtrim($appBaseUrl, '/');
        }
        $token      = $this->params->get('database_token');
        $baseDomain = $this->params->get('base_domain');
        return 'https://' . $token . '.' . $baseDomain;
    }

    public function validateDownloadSignature(int $documentId, int $expires, string $sig): bool
    {
        if (time() > $expires) {
            return false;
        }
        $expected = hash_hmac('sha256', "portal_doc:{$documentId}:{$expires}", $this->getSecret());
        return hash_equals($expected, $sig);
    }

    private function getSecret(): string
    {
        return $this->params->get('kernel.secret');
    }
}
