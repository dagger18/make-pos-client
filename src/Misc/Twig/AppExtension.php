<?php

namespace App\Misc\Twig;

use App\Module\Core\Service\ConfigService;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

class AppExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly ConfigService $configService,
        private readonly ParameterBagInterface $params,
    ) {}

    public function getGlobals(): array
    {

        return [
            'bo_client_url' => $this->getBoUrl(),
        ];
    }

    private function getBoUrl(): string
    {
        $appBaseUrl = $this->params->get('app_base_url');
        if (!empty($appBaseUrl)) {
            $domain = rtrim($appBaseUrl, '/');
        } else {
            $dbToken    = $this->params->get('database_token');
            $baseDomain = $this->params->get('base_domain');
            $domain     = 'https://' . $dbToken . '.' . $baseDomain;
        }

        $parsed = parse_url($domain);
        $origin = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '');
        if (!empty($parsed['port'])) {
            $origin .= ':' . $parsed['port'];
        }
        return $origin;
    }
}
