<?php
namespace App\Module\Crm\Controller;

use App\Module\Crm\Entity\AgentProfile;
use App\Module\Crm\Repository\AgentProfileRepository;
use App\Module\Carrier\Repository\ProviderRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Misc\Attribute\AppModule;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

#[Route('/agent-profile')]
#[IsGranted('ROLE_USER')]
#[AppModule('crm')]
class AgentProfileController extends AbstractController
{
    public function __construct(
        private AgentProfileRepository $repository,
        private ProviderRepository $providerRepository,
        private NormalizerInterface $serializer,
    ) {}

    #[Route('/{providerId}', methods: ['GET'])]
    public function GET(int $providerId): JsonResponse
    {
        $profile = $this->repository->find($providerId);
        return $this->json($profile ? $this->serializer->normalize($profile, null, ['groups' => ['list']]) : null);
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
            $profile = new AgentProfile();
            $profile->setProvider($provider);
        }
        $data = $request->request->all();
        if (isset($data['network'])) $profile->setNetwork($data['network'] ?: null);
        if (isset($data['agentCode'])) $profile->setAgentCode($data['agentCode'] ?: null);
        if (isset($data['coverageCountries'])) $profile->setCoverageCountries($data['coverageCountries'] ? json_decode($data['coverageCountries'], true) : null);
        if (isset($data['modesHandled'])) $profile->setModesHandled($data['modesHandled'] ? json_decode($data['modesHandled'], true) : null);
        if (isset($data['commissionRate'])) $profile->setCommissionRate($data['commissionRate'] ?: null);
        if (isset($data['settlementCurrency'])) $profile->setSettlementCurrency($data['settlementCurrency'] ?: null);
        if (isset($data['settlementTerms'])) $profile->setSettlementTerms($data['settlementTerms'] ?: null);
        if (isset($data['performanceScore'])) $profile->setPerformanceScore($data['performanceScore'] ?: null);
        return $this->json($this->serializer->normalize($this->repository->save($profile), null, ['groups' => ['list']]));
    }
}
