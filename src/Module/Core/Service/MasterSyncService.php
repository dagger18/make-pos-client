<?php

namespace App\Module\Core\Service;

use App\Module\Core\Enum\Magnum;
use App\Module\Core\Repository\UserRepository;
use App\Module\Tax\Entity\CustomsEntry;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class MasterSyncService
{
    public function __construct(
        private ConfigService $configService,
        private UserRepository $userRepository,
        private HttpClientInterface $httpClient,
        private ParameterBagInterface $params,
        private InterServiceTokenService $interServiceTokenService,
    ) {}

    public function searchCurrencies(string $q, int $limit, int $offset = 0, array $excludeIds = []): array
    {
        $params = ['q' => $q, 'limit' => $limit, 'offset' => $offset];
        if (!empty($excludeIds)) {
            $params['excludeIds'] = implode(',', $excludeIds);
        }

        try {
            $response = $this->httpClient->request(
                'GET',
                rtrim($this->params->get('master_api_url'), '/') . '/public/currency/search',
                [
                    'headers' => ['X-Service-Token' => $this->interServiceTokenService->generate()],
                    'query' => $params,
                    'timeout' => 5,
                ]
            );
            $data = $response->toArray();
            return $data['list'] ?? [];
        } catch (\Throwable) {
            return [];
        }
    }

    public function syncCurrentUsers(): void
    {
        $orgConfig = $this->configService->getConfigValue('organization', isJson: true);
        $token = $orgConfig['token'] ?? null;
        if (!$token) {
            return;
        }

        $count = count($this->userRepository->getAll(['except_id' => Magnum::SYSTEM_ADMIN_ID], 'ENTITY'));

        try {
            $this->httpClient->request(
                'PUT',
                rtrim($this->params->get('master_api_url'), '/') . '/public/organization/' . $token . '/current-users',
                [
                    'headers' => ['X-Service-Token' => $this->interServiceTokenService->generate()],
                    'json' => ['count' => $count],
                ]
            );
        } catch (\Throwable) {
            // non-blocking
        }
    }

    public function searchVesselSailings(string $pol, string $pod, string $etdFrom, string $etdTo): array
    {
        try {
            $response = $this->httpClient->request(
                'GET',
                rtrim($this->params->get('master_api_url'), '/') . '/public/vessel-sailing/search',
                [
                    'headers' => ['X-Service-Token' => $this->interServiceTokenService->generate()],
                    'query' => [
                        'pol' => $pol,
                        'pod' => $pod,
                        'etd_from' => $etdFrom,
                        'etd_to' => $etdTo,
                    ],
                    'timeout' => 15,
                ]
            );
            $data = $response->toArray();
            return $data['list'] ?? [];
        } catch (\Throwable) {
            return [];
        }
    }

    public function searchFlightSchedules(string $origin, string $destination, string $date): array
    {
        try {
            $response = $this->httpClient->request(
                'GET',
                rtrim($this->params->get('master_api_url'), '/') . '/public/flight-schedule/search',
                [
                    'headers' => ['X-Service-Token' => $this->interServiceTokenService->generate()],
                    'query' => [
                        'origin' => $origin,
                        'destination' => $destination,
                        'date' => $date,
                    ],
                    'timeout' => 15,
                ]
            );
            $data = $response->toArray();
            return $data['list'] ?? [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Batch-fetches latest market rates from master API for a list of lanes.
     * Each lane is { pol, pod, mode, container_type }.
     * Returns a map keyed by "pol|pod|mode|container_type" → { rate_amount, index_source, rate_date, currency }.
     * Returns empty array if master API is unreachable or the endpoint doesn't exist yet.
     */
    public function getMarketRates(array $lanes): array
    {
        if (empty($lanes)) {
            return [];
        }
        try {
            $response = $this->httpClient->request(
                'POST',
                rtrim($this->params->get('master_api_url'), '/') . '/public/rate-benchmark/market-rates',
                [
                    'headers' => ['X-Service-Token' => $this->interServiceTokenService->generate()],
                    'json'    => ['lanes' => $lanes],
                    'timeout' => 15,
                ]
            );
            return $response->toArray() ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Fetches market rate trend data from master API for a specific trade lane.
     * Returns array of { rate_date, index_source, rate_amount, currency, week_change, week_change_pct }.
     * Returns empty array if master API is unreachable or the endpoint doesn't exist yet.
     */
    public function getMarketRateTrend(string $pol, string $pod, ?string $containerType, int $days = 180): array
    {
        try {
            $query = ['pol' => $pol, 'pod' => $pod, 'days' => $days];
            if ($containerType) {
                $query['container_type'] = $containerType;
            }
            $response = $this->httpClient->request(
                'GET',
                rtrim($this->params->get('master_api_url'), '/') . '/public/rate-benchmark/trend',
                [
                    'headers' => ['X-Service-Token' => $this->interServiceTokenService->generate()],
                    'query'   => $query,
                    'timeout' => 15,
                ]
            );
            $data = $response->toArray();
            return $data['list'] ?? (is_array($data) ? $data : []);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Submits a customs declaration to the customs authority via the master API.
     * Payload is built from the CustomsEntry object.
     * Returns: { declaration_number, submission_ref, status, message } or [] on failure.
     */
    public function submitCustomsDeclaration(CustomsEntry $entry): array
    {
        try {
            $response = $this->httpClient->request(
                'POST',
                rtrim($this->params->get('master_api_url'), '/') . '/public/customs/submit',
                [
                    'headers' => ['X-Service-Token' => $this->interServiceTokenService->generate()],
                    'json'    => [
                        'entry_id'       => $entry->getId(),
                        'entry_type'     => $entry->getEntryType(),
                        'entry_mode'     => $entry->getEntryMode(),
                        'system_code'    => $entry->getSystemCode(),
                        'country_code'   => $entry->getCountryCode(),
                        'shipment_id'    => $entry->getShipment()->getId(),
                        'cif_value'      => $entry->getCifValue(),
                        'value_currency' => $entry->getValueCurrency(),
                        'customs_office' => $entry->getCustomsOffice(),
                    ],
                    'timeout' => 30,
                ]
            );
            return $response->toArray() ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Polls the customs authority for the latest status of a declaration.
     * Returns: { status, total_duty, released_at, message } or [] on failure.
     */
    public function checkCustomsStatus(string $declarationNumber, string $systemCode): array
    {
        try {
            $response = $this->httpClient->request(
                'GET',
                rtrim($this->params->get('master_api_url'), '/') . '/public/customs/status',
                [
                    'headers' => ['X-Service-Token' => $this->interServiceTokenService->generate()],
                    'query'   => ['declaration_number' => $declarationNumber, 'system_code' => $systemCode],
                    'timeout' => 15,
                ]
            );
            return $response->toArray() ?: [];
        } catch (\Throwable) {
            return [];
        }
    }
}
