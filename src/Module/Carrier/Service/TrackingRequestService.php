<?php
namespace App\Module\Carrier\Service;

use App\Module\Core\Service\BaseService;

use App\Module\Operations\Entity\Shipment;
use App\Module\Carrier\Entity\TrackingRequest;
use App\Module\Carrier\Repository\TrackingRequestRepository;
use App\Module\Core\Service\InterServiceTokenService;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TrackingRequestService extends BaseService
{
    public function __construct(
        protected BaseService $baseService,
        public TrackingRequestRepository $repository,
        private readonly HttpClientInterface $httpClient,
        private readonly InterServiceTokenService $tokenService,
    ) {
        $this->reflectFromParent($baseService);
    }

    public function createForShipment(Shipment $shipment, array $body): TrackingRequest
    {
        $request = new TrackingRequest();
        $request->setShipment($shipment);
        $request->setTrackingType($body['trackingType']);
        $request->setTrackingRef($body['trackingRef']);
        $request->setCarrierScac($body['carrierScac'] ?? null);
        $request->setWebhookSecret(bin2hex(random_bytes(24)));

        $request = $this->repository->save($request);

        $this->registerWithMasterApi($request);
        return $request;
    }

    private function registerWithMasterApi(TrackingRequest $request): void
    {
        $masterUrl   = rtrim($this->params->get('master_api_url'), '/');
        $callbackUrl = $this->getDomain() . '/tracking-webhook/' . $request->getId();

        try {
            $response = $this->httpClient->request('POST', $masterUrl . '/api/public/tracking-job', [
                'headers' => ['X-Service-Token' => $this->tokenService->generate()],
                'json' => [
                    'trackingType'        => $request->getTrackingType(),
                    'trackingRef'         => $request->getTrackingRef(),
                    'carrierScac'         => $request->getCarrierScac(),
                    'callbackUrl'         => $callbackUrl,
                    'callbackSecret'      => $request->getWebhookSecret(),
                    'checkFrequencyHours' => 4,
                ],
                'timeout' => 10,
            ]);
            $data = $response->toArray();
            $request->setMasterJobId($data['id'] ?? null);
            $this->repository->save($request);
        } catch (\Throwable) {
            // Master API registration is best-effort; tracking will be un-polled until resolved
        }
    }

    public function updateStatus(TrackingRequest $request, string $status): void
    {
        $request->setStatus($status);
        $this->repository->save($request);

        if ($request->getMasterJobId()) {
            $masterUrl = rtrim($this->params->get('master_api_url'), '/');
            try {
                $this->httpClient->request('PATCH', $masterUrl . '/api/public/tracking-job/' . $request->getMasterJobId(), [
                    'headers' => ['X-Service-Token' => $this->tokenService->generate()],
                    'json'    => ['status' => $status],
                    'timeout' => 5,
                ]);
            } catch (\Throwable) {}
        }
    }

    public function deleteRequest(TrackingRequest $request): void
    {
        if ($request->getMasterJobId()) {
            $masterUrl = rtrim($this->params->get('master_api_url'), '/');
            try {
                $this->httpClient->request('DELETE', $masterUrl . '/api/public/tracking-job/' . $request->getMasterJobId(), [
                    'headers' => ['X-Service-Token' => $this->tokenService->generate()],
                    'timeout' => 5,
                ]);
            } catch (\Throwable) {}
        }
        $this->repository->delete($request);
    }
}
