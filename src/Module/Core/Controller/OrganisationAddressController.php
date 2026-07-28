<?php
namespace App\Module\Core\Controller;

use App\Module\Core\Entity\OrganisationAddress;
use App\Module\Core\Enum\AddressType;
use App\Module\Crm\Repository\ClientRepository;
use App\Module\Core\Repository\OrganisationAddressRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Misc\Attribute\AppModule;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/organisation-address')]
#[IsGranted('ROLE_USER')]
#[AppModule('core')]
class OrganisationAddressController extends AbstractController
{
    public function __construct(
        private OrganisationAddressRepository $repository,
        private ClientRepository $clientRepository,
        private SerializerInterface $serializer,
    ) {}

    #[Route('', methods: ['GET'])]
    public function LIST(Request $request): JsonResponse
    {
        $clientId = $request->query->getInt('clientId', 0);
        $addresses = $clientId
            ? $this->repository->findByClient($clientId)
            : [];
        return $this->json($this->serializer->normalize($addresses, null, ['groups' => ['list']]));
    }

    #[Route('', methods: ['POST'])]
    public function POST(Request $request): JsonResponse
    {
        $address = new OrganisationAddress();
        $this->hydrateFromRequest($address, $request->request->all());
        return $this->json(
            $this->serializer->normalize($this->repository->save($address), null, ['groups' => ['list']]),
            Response::HTTP_CREATED
        );
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function PUT(int $id, Request $request): JsonResponse
    {
        $address = $this->repository->find($id);
        if (!$address) {
            return $this->json(['error' => $this->trans('Not found')], Response::HTTP_NOT_FOUND);
        }
        $this->hydrateFromRequest($address, $request->request->all());
        return $this->json($this->serializer->normalize($this->repository->save($address), null, ['groups' => ['list']]));
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function DELETE(int $id): JsonResponse
    {
        $address = $this->repository->find($id);
        if (!$address) {
            return $this->json(['error' => $this->trans('Not found')], Response::HTTP_NOT_FOUND);
        }
        $em = $this->repository->getEntityManager();
        $em->remove($address);
        $em->flush();
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    private function hydrateFromRequest(OrganisationAddress $address, array $data): void
    {
        if (!empty($data['clientId'])) {
            $address->setClient($this->clientRepository->find((int)$data['clientId']));
        }
        if (isset($data['addressType'])) $address->setAddressType(AddressType::from($data['addressType']));
        if (isset($data['label'])) $address->setLabel($data['label'] ?: null);
        if (isset($data['addressLine1'])) $address->setAddressLine1($data['addressLine1']);
        if (isset($data['addressLine2'])) $address->setAddressLine2($data['addressLine2'] ?: null);
        if (isset($data['city'])) $address->setCity($data['city']);
        if (isset($data['state'])) $address->setState($data['state'] ?: null);
        if (isset($data['postalCode'])) $address->setPostalCode($data['postalCode'] ?: null);
        if (isset($data['country'])) $address->setCountry($data['country']);
        if (isset($data['isDefault'])) $address->setIsDefault((bool)$data['isDefault']);
        if (isset($data['notes'])) $address->setNotes($data['notes'] ?: null);
    }
}
