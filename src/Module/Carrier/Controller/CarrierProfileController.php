<?php
namespace App\Module\Carrier\Controller;

use App\Module\Carrier\Enum\CarrierType;

use App\Module\Carrier\Entity\CarrierProfile;
use App\Module\Carrier\Repository\CarrierProfileRepository;
use App\Module\Carrier\Repository\ProviderRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Misc\Attribute\AppModule;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/carrier-profile')]
#[IsGranted('ROLE_USER')]
#[AppModule('carrier')]
class CarrierProfileController extends AbstractController
{
    public function __construct(
        private CarrierProfileRepository $repository,
        private ProviderRepository $providerRepository,
        private SerializerInterface $serializer,
    ) {}

    #[Route('/{providerId}', methods: ['GET'])]
    public function GET(int $providerId): JsonResponse
    {
        $profile = $this->repository->find($providerId);
        if (!$profile) {
            return $this->json(null);
        }
        return $this->json($this->serializer->normalize($profile, null, ['groups' => ['list']]));
    }

    #[Route('/{providerId}', methods: ['PUT'])]
    public function PUT(int $providerId, Request $request): JsonResponse
    {
        $profile = $this->repository->find($providerId);
        if (!$profile) {
            $provider = $this->providerRepository->find($providerId);
            if (!$provider) {
                return $this->json(['error' => $this->trans('Provider not found')], Response::HTTP_NOT_FOUND);
            }
            $profile = new CarrierProfile();
            $profile->setProvider($provider);
        }
        $data = $request->request->all();
        if (isset($data['scacCode'])) $profile->setScacCode($data['scacCode'] ?: null);
        if (isset($data['iataCode'])) $profile->setIataCode($data['iataCode'] ?: null);
        if (isset($data['carrierType'])) $profile->setCarrierType($data['carrierType'] ? CarrierType::from($data['carrierType']) : null);
        if (isset($data['alliance'])) $profile->setAlliance($data['alliance'] ?: null);
        if (isset($data['bookingPlatform'])) $profile->setBookingPlatform($data['bookingPlatform'] ?: null);
        if (isset($data['bookingEmail'])) $profile->setBookingEmail($data['bookingEmail'] ?: null);
        if (isset($data['siEmail'])) $profile->setSiEmail($data['siEmail'] ?: null);
        if (isset($data['amsFiler'])) $profile->setAmsFiler($data['amsFiler'] ?: null);
        if (isset($data['preferredPayment'])) $profile->setPreferredPayment($data['preferredPayment'] ?: null);
        return $this->json($this->serializer->normalize($this->repository->save($profile), null, ['groups' => ['list']]));
    }
}
