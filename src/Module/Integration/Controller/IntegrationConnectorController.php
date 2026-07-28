<?php
declare(strict_types=1);
namespace App\Module\Integration\Controller;

use App\Misc\Attribute\AppModule;
use App\Module\Integration\Entity\IntegrationConnector;
use App\Module\Integration\Repository\IntegrationConnectorRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/integration/connectors')]
#[IsGranted('ROLE_USER')]
#[AppModule('integration')]
class IntegrationConnectorController extends AbstractController
{
    public function __construct(
        private readonly IntegrationConnectorRepository $repo,
    ) {}

    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $connectorType = $request->query->get('connector_type') ?: null;
        $isActiveParam = $request->query->get('is_active');
        $isActive = $isActiveParam !== null ? filter_var($isActiveParam, FILTER_VALIDATE_BOOLEAN) : null;

        $items = $this->repo->findFiltered($connectorType, $isActive);

        return $this->json(array_map(fn($c) => $this->serialize($c), $items));
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $body = json_decode($request->getContent(), true) ?? [];

        if (empty($body['connectorType']) || empty($body['partnerName']) || empty($body['protocol'])) {
            return $this->json(['error' => $this->trans('connectorType, partnerName and protocol are required.')], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $connector = new IntegrationConnector();
        $this->hydrate($connector, $body);

        $this->repo->save($connector, true);

        return $this->json($this->serialize($connector), Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $connector = $this->repo->find($id);
        if (!$connector) {
            throw $this->createNotFoundException();
        }

        $body = json_decode($request->getContent(), true) ?? [];
        $this->hydrate($connector, $body);

        $this->repo->save($connector, true);

        return $this->json($this->serialize($connector));
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $connector = $this->repo->find($id);
        if (!$connector) {
            throw $this->createNotFoundException();
        }

        $this->repo->remove($connector, true);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    private function serialize(IntegrationConnector $c): array
    {
        return [
            'id'              => $c->getId(),
            'connectorType'   => $c->getConnectorType(),
            'partnerName'     => $c->getPartnerName(),
            'protocol'        => $c->getProtocol(),
            'baseUrl'         => $c->getBaseUrl(),
            'authType'        => $c->getAuthType(),
            'credentialsRef'  => $c->getCredentialsRef(),
            'isActive'        => $c->isActive(),
            'capabilities'    => $c->getCapabilities(),
            'testMode'        => $c->isTestMode(),
            'lastPingAt'      => $c->getLastPingAt()?->format(\DateTimeInterface::ATOM),
            'lastPingStatus'  => $c->getLastPingStatus(),
            'createdAt'       => $c->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            'updatedAt'       => $c->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    private function hydrate(IntegrationConnector $c, array $body): void
    {
        if (array_key_exists('connectorType', $body) && $body['connectorType'] !== '') {
            $c->setConnectorType($body['connectorType']);
        }
        if (array_key_exists('partnerName', $body) && $body['partnerName'] !== '') {
            $c->setPartnerName($body['partnerName']);
        }
        if (array_key_exists('protocol', $body) && $body['protocol'] !== '') {
            $c->setProtocol($body['protocol']);
        }

        $nullableStrings = ['baseUrl', 'authType', 'credentialsRef', 'lastPingStatus'];
        foreach ($nullableStrings as $f) {
            if (array_key_exists($f, $body)) {
                $setter = 'set' . ucfirst($f);
                $c->$setter($body[$f] !== '' ? $body[$f] : null);
            }
        }

        if (array_key_exists('capabilities', $body) && is_array($body['capabilities'])) {
            $c->setCapabilities($body['capabilities']);
        }
        if (array_key_exists('isActive', $body)) {
            $c->setIsActive((bool) $body['isActive']);
        }
        if (array_key_exists('testMode', $body)) {
            $c->setTestMode((bool) $body['testMode']);
        }
    }
}
