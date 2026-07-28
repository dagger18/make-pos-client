<?php

namespace App\Module\Core\Controller;

use App\Module\Core\Controller\CrudController;

use App\Misc\Attribute\AppModule;
use App\Module\Core\Service\BaseService;
use App\Module\Core\Service\ConfigService;
use App\Module\Quote\Service\QuoteCodeGeneratorService;
use App\Module\Operations\Service\ShipmentIdGeneratorService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/config')]
#[IsGranted('ROLE_USER')]
#[AppModule('core')]
class ConfigController extends CrudController
{
    public function __construct(
        protected BaseService $baseService,
        protected ConfigService $configService,
    ) {}

    #[Route('/{name}', methods: ['GET'])]
    public function get(string $name): JsonResponse
    {
        $value = $this->configService->getConfigValue($name);
        return $this->json(['name' => $name, 'value' => $value]);
    }

    #[Route('/{name}', methods: ['PUT'])]
    public function put(string $name, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $value = (string) ($data['value'] ?? '');
        $config = $this->configService->setConfig($name, $value, 'setting');
        return $this->json(['name' => $name, 'value' => $config->getValue()]);
    }

    #[Route('/shipment-id-format/tokens', methods: ['GET'])]
    public function shipmentIdTokens(): JsonResponse
    {
        return $this->json([
            'tokens' => [
                ['token' => '$GlobalCounter',  'description' => 'Global counter (8 digits, zero-padded)'],
                ['token' => '$MonthlyCounter', 'description' => 'Monthly counter (5 digits, zero-padded, resets each month)'],
                ['token' => '$Branch',         'description' => 'Branch code (3 chars)'],
                ['token' => '$ShipType',       'description' => 'Shipment direction: EXP / IMP / XTD / DOM / TSH'],
                ['token' => '$TransType',      'description' => 'Transport mode: OCN / AIR / RD / RAL / COU / MMD'],
                ['token' => '$ServiceType',    'description' => 'Service type short code: FCL / LCL / DRT / CSL / FTL / LTL …'],
                ['token' => '$YearMonth',      'description' => 'Year and month, e.g. 202604'],
                ['token' => '$OrgCode',        'description' => 'Origin port/location code'],
                ['token' => '$DestCode',       'description' => 'Destination port/location code'],
            ],
            'default' => ShipmentIdGeneratorService::DEFAULT_FORMAT,
        ]);
    }

    #[Route('/quote-code-format/tokens', methods: ['GET'])]
    public function quoteCodeTokens(): JsonResponse
    {
        return $this->json([
            'tokens' => [
                ['token' => '$GlobalCounter',  'description' => 'Global counter (8 digits, zero-padded)'],
                ['token' => '$MonthlyCounter', 'description' => 'Monthly counter (5 digits, zero-padded, resets each month)'],
                ['token' => '$Branch',         'description' => 'Branch code (3 chars)'],
                ['token' => '$TransType',      'description' => 'Transport mode: OCN / AIR / RD / RAL / COU / MMD'],
                ['token' => '$YearMonth',      'description' => 'Year and month, e.g. 202604'],
                ['token' => '$OrgCode',        'description' => 'Origin port/location code'],
                ['token' => '$DestCode',       'description' => 'Destination port/location code'],
            ],
            'default' => QuoteCodeGeneratorService::DEFAULT_FORMAT,
        ]);
    }
}
