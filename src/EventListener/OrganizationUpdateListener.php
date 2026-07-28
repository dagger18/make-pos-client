<?php

namespace App\EventListener;

use App\Module\Carrier\Entity\Provider;
use App\Module\Core\Enum\Magnum;
use App\Module\Core\Service\ConfigService;
use App\Module\Core\Service\InterServiceTokenService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsEntityListener(event: Events::postUpdate, entity: Provider::class)]
class OrganizationUpdateListener
{
    public function __construct(
        private ConfigService $configService,
        private HttpClientInterface $httpClient,
        private ParameterBagInterface $params,
        private LoggerInterface $logger,
        private InterServiceTokenService $interServiceTokenService,
    ) {}

    public function postUpdate(Provider $entity, PostUpdateEventArgs $args): void
    {
        if ($entity->getId() !== Magnum::COMPANY_PROVIDER_ID) {
            return;
        }

        // Read changeSet before any DB operation — setConfig() flushes the EM,
        // which clears UnitOfWork dirty tracking and makes getEntityChangeSet() return empty.
        /** @var EntityManager $entityManager */
        $entityManager = $args->getObjectManager();
        $changeSet = $entityManager->getUnitOfWork()->getEntityChangeSet($entity);

        $config = $this->configService->getConfigValue('organization', isJson: true) ?? [];
        if (empty($config['providerReady'])) {
            $config['providerReady'] = true;
            $this->configService->setConfig('organization', json_encode($config));
        }

        if (!array_key_exists('name', $changeSet)) {
            return;
        }

        $token = $config['token'] ?? null;
        if (!$token) {
            return;
        }

        try {
            $this->httpClient->request('PUT', sprintf(
                '%s/public/organization/%s/name',
                $this->params->get('master_api_url'),
                $token,
            ), [
                'headers' => ['X-Service-Token' => $this->interServiceTokenService->generate()],
                'json' => ['name' => $changeSet['name'][1]],
            ])->getStatusCode();
        } catch (\Throwable $e) {
            $this->logger->error('Failed to sync organization name to master API: ' . $e->getMessage());
        }
    }
}
