<?php
namespace App\Module\Core\Service;

use App\Module\Core\Service\BaseService;
use App\Module\Core\Service\InterServiceTokenService;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class MasterService extends BaseService
{
    public function __construct(
        protected BaseService $baseService,
        protected HttpClientInterface $client,
        protected ConfigService $configService,
        private InterServiceTokenService $interServiceTokenService,
    ) {
        $this->reflectFromParent($baseService);
    }

    public function getMasterLoginLink(string $orgToken): ?string
    {
        $response = $this->client->request(
            'GET',
            rtrim($this->params->get('master_api_url'), '/') . '/public/organization/' . $orgToken . '/master-login-link',
            [
                'headers' => [
                    'Accept' => 'application/json',
                    'X-Service-Token' => $this->interServiceTokenService->generate(),
                ],
            ]
        );

        if ($response->getStatusCode() === 200) {
            return $response->toArray()['link'] ?? null;
        }

        return null;
    }
}
