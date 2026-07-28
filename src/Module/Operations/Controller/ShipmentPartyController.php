<?php

namespace App\Module\Operations\Controller;

use App\Module\Crm\Entity\Client;

use App\Module\Operations\Entity\ShipmentParty;
use App\Module\Operations\Enum\PartyRole;
use App\Module\Crm\Repository\ClientRepository;
use App\Module\Crm\Repository\ContactRepository;
use App\Module\Operations\Repository\ShipmentPartyRepository;
use App\Module\Operations\Repository\ShipmentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Misc\Attribute\AppModule;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/shipment/{shipmentId}/parties')]
#[IsGranted('ROLE_USER')]
#[AppModule('operations')]
class ShipmentPartyController extends AbstractController
{
    public function __construct(
        private readonly ShipmentPartyRepository $partyRepository,
        private readonly ShipmentRepository $shipmentRepository,
        private readonly ClientRepository $clientRepository,
        private readonly ContactRepository $contactRepository,
    ) {}

    #[Route('', methods: ['GET'])]
    public function list(int $shipmentId): JsonResponse
    {
        return $this->json(array_map(
            fn($p) => $this->serialize($p),
            $this->partyRepository->findByShipment($shipmentId)
        ));
    }

    #[Route('', methods: ['POST'])]
    public function create(int $shipmentId, Request $request): JsonResponse
    {
        $shipment = $this->shipmentRepository->find($shipmentId);
        if (!$shipment) throw $this->createNotFoundException();

        $body = json_decode($request->getContent(), true) ?? [];
        $role = PartyRole::tryFrom($body['role'] ?? '');
        if (!$role) {
            return $this->json(['error' => $this->trans('Invalid role.')], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($this->partyRepository->findOneByShipmentAndRole($shipmentId, $role)) {
            return $this->json(['error' => $this->trans('Role already assigned.')], Response::HTTP_CONFLICT);
        }

        $party = new ShipmentParty();
        $party->setShipment($shipment)->setRole($role);
        $this->hydrate($party, $body);
        $this->partyRepository->save($party);

        return $this->json($this->serialize($party), Response::HTTP_CREATED);
    }

    #[Route('/{role}', methods: ['PUT'])]
    public function update(int $shipmentId, string $role, Request $request): JsonResponse
    {
        $partyRole = PartyRole::tryFrom($role);
        if (!$partyRole) throw $this->createNotFoundException();

        $party = $this->partyRepository->findOneByShipmentAndRole($shipmentId, $partyRole);
        if (!$party) throw $this->createNotFoundException();

        $body = json_decode($request->getContent(), true) ?? [];
        $this->hydrate($party, $body);
        $this->partyRepository->save($party);

        return $this->json($this->serialize($party));
    }

    #[Route('/{role}', methods: ['DELETE'])]
    public function delete(int $shipmentId, string $role): JsonResponse
    {
        $partyRole = PartyRole::tryFrom($role);
        if (!$partyRole) throw $this->createNotFoundException();

        $party = $this->partyRepository->findOneByShipmentAndRole($shipmentId, $partyRole);
        if (!$party) throw $this->createNotFoundException();

        $this->partyRepository->delete($party);
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    private function hydrate(ShipmentParty $party, array $body): void
    {
        if (isset($body['clientId'])) {
            $client = $body['clientId'] ? $this->clientRepository->find((int) $body['clientId']) : null;
            $party->setClient($client);
            $party->setAddressSnapshot($client ? $this->buildSnapshot($client) : []);
        }
        if (isset($body['contactId'])) {
            $contact = $body['contactId'] ? $this->contactRepository->find((int) $body['contactId']) : null;
            $party->setContact($contact);
        }
        if (array_key_exists('reference', $body))    $party->setReference($body['reference'] ?: null);
        if (isset($body['isAlsoNotify']))             $party->setIsAlsoNotify((bool) $body['isAlsoNotify']);
        if (isset($body['addressSnapshot']) && is_array($body['addressSnapshot'])) {
            $party->setAddressSnapshot($body['addressSnapshot']);
        }
    }

    private function buildSnapshot(Client $client): array
    {
        return [
            'name'    => $client->getName(),
            'address' => $client->getAddress(),
            'city'    => $client->getCity(),
            'country' => $client->getCountry(),
            'phone'   => $client->getPhone(),
            'email'   => $client->getDefaultContact()?->getEmail(),
            'taxId'   => null,
        ];
    }

    private function serialize(ShipmentParty $party): array
    {
        $c = $party->getClient();
        $ct = $party->getContact();
        return [
            'role'            => $party->getRole()->value,
            'roleLabel'       => $party->getRole()->label(),
            'client'          => $c ? ['id' => $c->getId(), 'name' => $c->getName()] : null,
            'contact'         => $ct ? [
                'id'    => $ct->getId(),
                'name'  => trim(($ct->getFirstName() ?? '') . ' ' . ($ct->getLastName() ?? '')),
                'email' => $ct->getEmail(),
                'phone' => $ct->getPhone(),
            ] : null,
            'reference'       => $party->getReference(),
            'isAlsoNotify'    => $party->isAlsoNotify(),
            'addressSnapshot' => $party->getAddressSnapshot(),
        ];
    }
}
