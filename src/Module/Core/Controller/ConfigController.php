<?php

namespace App\Module\Core\Controller;

use App\Module\Core\Controller\CrudController;

use App\Misc\Attribute\AppModule;
use App\Module\Core\Service\BaseService;
use App\Module\Core\Service\ConfigService;
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


}
