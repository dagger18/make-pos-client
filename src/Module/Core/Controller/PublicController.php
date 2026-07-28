<?php

namespace App\Module\Core\Controller;


use App\Module\Core\Enum\Country;
use App\Module\Core\Enum\Magnum;
use App\Module\Finance\Repository\CurrencyRepository;
use App\Module\Carrier\Repository\ProviderRepository;
use App\Module\Core\Service\ConfigService;
use App\Module\Core\Service\MediaService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Cache\Adapter\TraceableAdapter;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class PublicController extends AbstractController
{
    /**
     * @param TraceableAdapter $cache
    */
    #[Route('/public/organization', methods: ['GET'])]
    public function getOrganization(
        ConfigService $configService,
        ProviderRepository $providerRepository,
    ): JsonResponse
    {
        $provider = $providerRepository->find(Magnum::COMPANY_PROVIDER_ID);
        $name = $provider?->getName() ?? null;
        return $this->json([
            'name' => $name,
        ]);
    }
    /**
     * @param TraceableAdapter $cache
    */
    #[Route('/public/countries', methods: ['GET'])]
    public function getCountries(TranslatorInterface $translator, Request $request
    ): JsonResponse
    {
        return $this->json(Country::countryList($translator, $request->getLocale()));
    }
    /**
     * @param TraceableAdapter $cache
    */
    #[Route('/public/currencies', methods: ['GET'])]
    public function getCurrencies(Request $request, CurrencyRepository $currencyRepository
    ): JsonResponse
    {
        $data = $currencyRepository->getList($request->query->all(), 'list');
        return $this->json($data);
    }

    #[Route('/public/media/serve/{token}', methods: ['GET'])]
    public function serveMedia(string $token, MediaService $mediaService): Response
    {
        try {
            $url = $mediaService->consumeDownloadToken($token);
        } catch (\InvalidArgumentException) {
            throw $this->createNotFoundException('Invalid or expired download link');
        }
        return new RedirectResponse($url);
    }

}
