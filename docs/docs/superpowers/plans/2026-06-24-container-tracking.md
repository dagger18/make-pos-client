# Container Tracking Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement container/shipment tracking across three repos: master API polls carriers on a schedule, client API registers jobs and receives webhook callbacks, client BO displays tracking subscriptions and raw events.

**Architecture:** Client API registers a `TrackingRequest` locally then POSTs a job to Master API (`/api/public/tracking-job`). Master API polls carrier APIs via Symfony Messenger + a scheduler command, then POSTs milestone events back to the client API webhook (`/tracking-webhook/{id}`) using a per-request secret. Client API normalises events via `CarrierEventMapping` and writes idempotent `ShipmentMilestone` records (never overwriting MANUAL source).

**Tech Stack:** PHP 8.2 / Symfony 7.1, Doctrine ORM, Symfony Messenger (async), Symfony Console commands, Vue 3 + Vuetify.

---

## File Map

### Master API (`D:\Projects\make-cargo`)
| Action | Path |
|--------|------|
| Create | `src/Entity/TrackingJob.php` |
| Create | `src/Repository/TrackingJobRepository.php` |
| Create | `migrations/Version20260624010000.php` |
| Create | `src/Service/Tracking/CarrierConnectorInterface.php` |
| Create | `src/Service/Tracking/StubCarrierConnector.php` |
| Create | `src/Service/Tracking/MaerskConnector.php` |
| Create | `src/Service/Tracking/TrackingDispatcherService.php` |
| Create | `src/Service/Tracking/TrackingCallbackService.php` |
| Create | `src/Message/TrackingJobMessage.php` |
| Create | `src/MessageHandler/TrackingJobMessageHandler.php` |
| Create | `src/Command/TrackingSchedulerCommand.php` |
| Create | `src/Controller/Http/TrackingJobController.php` |
| Modify | `config/packages/messenger.yaml` — add routing for TrackingJobMessage |

### Client API (`d:\Projects\make-cargo-client`)
| Action | Path |
|--------|------|
| Create | `src/Entity/TrackingRequest.php` |
| Create | `src/Entity/TrackingEventRaw.php` |
| Create | `src/Entity/CarrierEventMapping.php` |
| Create | `src/Repository/TrackingRequestRepository.php` |
| Create | `src/Repository/TrackingEventRawRepository.php` |
| Create | `src/Repository/CarrierEventMappingRepository.php` |
| Create | `migrations/mysql/Version20260624110000.php` |
| Create | `migrations/sqlite/Version20260624110000.php` |
| Create | `migrations/mysql/Version20260624120000.php` |
| Create | `migrations/sqlite/Version20260624120000.php` |
| Create | `migrations/mysql/Version20260624130000.php` |
| Create | `migrations/sqlite/Version20260624130000.php` |
| Create | `config/serializer_groups/TrackingRequest.yaml` |
| Create | `config/serializer_groups/TrackingEventRaw.yaml` |
| Create | `config/serializer_groups/CarrierEventMapping.yaml` |
| Create | `src/Service/TrackingRequestService.php` |
| Create | `src/Service/TrackingEventRawService.php` |
| Create | `src/Service/CarrierEventMappingService.php` |
| Create | `src/Service/TrackingMilestoneWriterService.php` |
| Modify | `config/services.yaml` — register 4 new services |
| Create | `src/Controller/Api/TrackingRequestController.php` |
| Create | `src/Controller/Api/CarrierEventMappingController.php` |
| Create | `src/Controller/Api/TrackingWebhookController.php` |

### Client BO (`d:\Projects\make-cargo-client-bo`)
| Action | Path |
|--------|------|
| Create | `src/services/library/CarrierEventMappingService.js` |
| Create | `src/config/forms/library/CarrierEventMapping.js` |
| Create | `src/config/tables/library/CarrierEventMapping.js` |
| Create | `src/views/library/CarrierEventMappingForm.vue` |
| Create | `src/pages/library/carrier-event-mapping.vue` |
| Create | `src/services/TrackingRequestService.js` |
| Create | `src/config/tables/TrackingRequest.js` |
| Create | `src/config/tables/TrackingEventRaw.js` |
| Create | `src/views/shipment/ShipmentTrackingSubscriptions.vue` |
| Create | `src/views/shipment/ShipmentTrackingEvents.vue` |
| Modify | `src/views/shipment/ShipmentTracking.vue` — add two new tabs |
| Modify | `src/config/navigation/index.js` — add Carrier Event Mappings |

---

## Task 1: Master API — TrackingJob Entity + Migration

**Files:**
- Create: `D:\Projects\make-cargo\src\Entity\TrackingJob.php`
- Create: `D:\Projects\make-cargo\src\Repository\TrackingJobRepository.php`
- Create: `D:\Projects\make-cargo\migrations\Version20260624010000.php`

- [ ] **Step 1: Create `src/Entity/TrackingJob.php`**

```php
<?php
declare(strict_types=1);
namespace App\Entity;

use App\Repository\TrackingJobRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TrackingJobRepository::class)]
#[ORM\Index(columns: ['status', 'next_check_at'], name: 'IDX_tracking_job_due')]
class TrackingJob
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 32)]
    private string $trackingType;

    #[ORM\Column(length: 64)]
    private string $trackingRef;

    #[ORM\Column(length: 8, nullable: true)]
    private ?string $carrierScac = null;

    #[ORM\Column(length: 16)]
    private string $status = 'ACTIVE';

    #[ORM\Column(length: 500)]
    private string $callbackUrl;

    #[ORM\Column(length: 64)]
    private string $callbackSecret;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $nextCheckAt = null;

    #[ORM\Column]
    private int $checkFrequencyHours = 4;

    #[ORM\Column]
    private int $errorCount = 0;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $lastError = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->nextCheckAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getTrackingType(): string { return $this->trackingType; }
    public function setTrackingType(string $v): static { $this->trackingType = $v; return $this; }
    public function getTrackingRef(): string { return $this->trackingRef; }
    public function setTrackingRef(string $v): static { $this->trackingRef = $v; return $this; }
    public function getCarrierScac(): ?string { return $this->carrierScac; }
    public function setCarrierScac(?string $v): static { $this->carrierScac = $v; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $v): static { $this->status = $v; return $this; }
    public function getCallbackUrl(): string { return $this->callbackUrl; }
    public function setCallbackUrl(string $v): static { $this->callbackUrl = $v; return $this; }
    public function getCallbackSecret(): string { return $this->callbackSecret; }
    public function setCallbackSecret(string $v): static { $this->callbackSecret = $v; return $this; }
    public function getNextCheckAt(): ?\DateTimeImmutable { return $this->nextCheckAt; }
    public function setNextCheckAt(?\DateTimeImmutable $v): static { $this->nextCheckAt = $v; return $this; }
    public function getCheckFrequencyHours(): int { return $this->checkFrequencyHours; }
    public function setCheckFrequencyHours(int $v): static { $this->checkFrequencyHours = $v; return $this; }
    public function getErrorCount(): int { return $this->errorCount; }
    public function setErrorCount(int $v): static { $this->errorCount = $v; return $this; }
    public function getLastError(): ?string { return $this->lastError; }
    public function setLastError(?string $v): static { $this->lastError = $v; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function scheduleNextCheck(): void
    {
        $this->nextCheckAt = new \DateTimeImmutable("+{$this->checkFrequencyHours} hours");
    }

    public function markFailed(string $error): void
    {
        $this->errorCount++;
        $this->lastError = $error;
        if ($this->errorCount >= 10) {
            $this->status = 'FAILED';
        }
        $this->scheduleNextCheck();
    }
}
```

- [ ] **Step 2: Create `src/Repository/TrackingJobRepository.php`**

```php
<?php
declare(strict_types=1);
namespace App\Repository;

use App\Entity\TrackingJob;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class TrackingJobRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TrackingJob::class);
    }

    /** @return TrackingJob[] */
    public function findDueJobs(int $limit = 50): array
    {
        return $this->createQueryBuilder('j')
            ->where('j.status = :active')
            ->andWhere('j.nextCheckAt <= :now OR j.nextCheckAt IS NULL')
            ->setParameter('active', 'ACTIVE')
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('j.nextCheckAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function save(TrackingJob $job): TrackingJob
    {
        $em = $this->getEntityManager();
        $em->persist($job);
        $em->flush();
        return $job;
    }

    public function delete(TrackingJob $job): void
    {
        $em = $this->getEntityManager();
        $em->remove($job);
        $em->flush();
    }
}
```

- [ ] **Step 3: Create `migrations/Version20260624010000.php`**

```php
<?php
declare(strict_types=1);
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260624010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create tracking_job table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE tracking_job (
            id INT NOT NULL AUTO_INCREMENT,
            tracking_type VARCHAR(32) NOT NULL,
            tracking_ref VARCHAR(64) NOT NULL,
            carrier_scac VARCHAR(8) DEFAULT NULL,
            status VARCHAR(16) NOT NULL DEFAULT 'ACTIVE',
            callback_url VARCHAR(500) NOT NULL,
            callback_secret VARCHAR(64) NOT NULL,
            next_check_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
            check_frequency_hours INT NOT NULL DEFAULT 4,
            error_count INT NOT NULL DEFAULT 0,
            last_error LONGTEXT DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
            PRIMARY KEY (id),
            INDEX IDX_tracking_job_due (status, next_check_at)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE tracking_job');
    }
}
```

- [ ] **Step 4: Commit**

```
git add src/Entity/TrackingJob.php src/Repository/TrackingJobRepository.php migrations/Version20260624010000.php
git commit -m "feat: add TrackingJob entity and migration (master API)"
```
_(Run in `D:\Projects\make-cargo`)_

---

## Task 2: Master API — Carrier Connector Infrastructure

**Files:**
- Create: `D:\Projects\make-cargo\src\Service\Tracking\CarrierConnectorInterface.php`
- Create: `D:\Projects\make-cargo\src\Service\Tracking\StubCarrierConnector.php`
- Create: `D:\Projects\make-cargo\src\Service\Tracking\MaerskConnector.php`

- [ ] **Step 1: Create `src/Service/Tracking/CarrierConnectorInterface.php`**

```php
<?php
declare(strict_types=1);
namespace App\Service\Tracking;

interface CarrierConnectorInterface
{
    public function supports(string $carrierScac): bool;

    /**
     * @return array<int, array{eventCode: string, eventDescription: string, eventDate: string, location: string|null}>
     */
    public function fetchEvents(string $trackingType, string $trackingRef): array;
}
```

- [ ] **Step 2: Create `src/Service/Tracking/StubCarrierConnector.php`**

```php
<?php
declare(strict_types=1);
namespace App\Service\Tracking;

class StubCarrierConnector implements CarrierConnectorInterface
{
    public function supports(string $carrierScac): bool
    {
        return $carrierScac === 'STUB';
    }

    public function fetchEvents(string $trackingType, string $trackingRef): array
    {
        return [
            [
                'eventCode'        => 'GATE_IN',
                'eventDescription' => 'Container gated in at origin terminal',
                'eventDate'        => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
                'location'         => 'VNSGN',
            ],
        ];
    }
}
```

- [ ] **Step 3: Create `src/Service/Tracking/MaerskConnector.php`**

```php
<?php
declare(strict_types=1);
namespace App\Service\Tracking;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class MaerskConnector implements CarrierConnectorInterface
{
    private const SCAC = 'MAEU';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {}

    public function supports(string $carrierScac): bool
    {
        return $carrierScac === self::SCAC;
    }

    public function fetchEvents(string $trackingType, string $trackingRef): array
    {
        // Maersk Track & Trace API v2
        // Endpoint: GET https://api.maersk.com/track/v2/shipments?transportDocumentReference={mbl}
        // or        GET https://api.maersk.com/track/v2/equipment-events?equipmentReference={container}
        // Requires: Consumer-Key header from Maersk developer portal
        // Stubbed: returns empty until MAERSK_API_KEY env var is wired up
        return [];
    }
}
```

- [ ] **Step 4: Commit**

```
git add src/Service/Tracking/
git commit -m "feat: add carrier connector interface and stub/Maersk connectors"
```
_(Run in `D:\Projects\make-cargo`)_

---

## Task 3: Master API — Dispatcher, Callback Services + Messenger

**Files:**
- Create: `D:\Projects\make-cargo\src\Service\Tracking\TrackingDispatcherService.php`
- Create: `D:\Projects\make-cargo\src\Service\Tracking\TrackingCallbackService.php`
- Create: `D:\Projects\make-cargo\src\Message\TrackingJobMessage.php`
- Create: `D:\Projects\make-cargo\src\MessageHandler\TrackingJobMessageHandler.php`
- Create: `D:\Projects\make-cargo\src\Command\TrackingSchedulerCommand.php`
- Modify: `D:\Projects\make-cargo\config\packages\messenger.yaml`

- [ ] **Step 1: Create `src/Message/TrackingJobMessage.php`**

```php
<?php
declare(strict_types=1);
namespace App\Message;

final class TrackingJobMessage
{
    public function __construct(
        public readonly int $jobId,
    ) {}
}
```

- [ ] **Step 2: Create `src/Service/Tracking/TrackingDispatcherService.php`**

```php
<?php
declare(strict_types=1);
namespace App\Service\Tracking;

use App\Entity\TrackingJob;

class TrackingDispatcherService
{
    /** @param iterable<CarrierConnectorInterface> $connectors */
    public function __construct(
        private readonly iterable $connectors,
    ) {}

    /**
     * @return array<int, array{eventCode: string, eventDescription: string, eventDate: string, location: string|null}>
     */
    public function fetchEvents(TrackingJob $job): array
    {
        $scac = $job->getCarrierScac() ?? 'STUB';

        foreach ($this->connectors as $connector) {
            if ($connector->supports($scac)) {
                return $connector->fetchEvents($job->getTrackingType(), $job->getTrackingRef());
            }
        }

        // fallback to stub
        foreach ($this->connectors as $connector) {
            if ($connector->supports('STUB')) {
                return $connector->fetchEvents($job->getTrackingType(), $job->getTrackingRef());
            }
        }

        return [];
    }
}
```

- [ ] **Step 3: Register connectors in `config/services.yaml`**

Add the following to `D:\Projects\make-cargo\config\services.yaml` (under the existing `services:` key):

```yaml
    App\Service\Tracking\TrackingDispatcherService:
        arguments:
            $connectors: !tagged_iterator app.tracking.connector

    App\Service\Tracking\StubCarrierConnector:
        tags: [ app.tracking.connector ]

    App\Service\Tracking\MaerskConnector:
        tags: [ app.tracking.connector ]
```

- [ ] **Step 4: Create `src/Service/Tracking/TrackingCallbackService.php`**

```php
<?php
declare(strict_types=1);
namespace App\Service\Tracking;

use App\Entity\TrackingJob;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TrackingCallbackService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {}

    /**
     * @param array<int, array{eventCode: string, eventDescription: string, eventDate: string, location: string|null}> $events
     */
    public function send(TrackingJob $job, array $events): void
    {
        if (empty($events)) {
            return;
        }

        $this->httpClient->request('POST', $job->getCallbackUrl(), [
            'headers' => [
                'X-Tracking-Secret' => $job->getCallbackSecret(),
                'Content-Type'      => 'application/json',
            ],
            'json' => [
                'source' => $job->getCarrierScac() ?? 'UNKNOWN',
                'events' => $events,
            ],
            'timeout' => 10,
        ]);
    }
}
```

- [ ] **Step 5: Create `src/MessageHandler/TrackingJobMessageHandler.php`**

```php
<?php
declare(strict_types=1);
namespace App\MessageHandler;

use App\Message\TrackingJobMessage;
use App\Repository\TrackingJobRepository;
use App\Service\Tracking\TrackingCallbackService;
use App\Service\Tracking\TrackingDispatcherService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class TrackingJobMessageHandler
{
    public function __construct(
        private readonly TrackingJobRepository    $repository,
        private readonly TrackingDispatcherService $dispatcher,
        private readonly TrackingCallbackService   $callback,
    ) {}

    public function __invoke(TrackingJobMessage $message): void
    {
        $job = $this->repository->find($message->jobId);
        if (!$job || $job->getStatus() !== 'ACTIVE') {
            return;
        }

        try {
            $events = $this->dispatcher->fetchEvents($job);
            $this->callback->send($job, $events);
            $job->setLastError(null);
        } catch (\Throwable $e) {
            $job->markFailed($e->getMessage());
            $this->repository->save($job);
            return;
        }

        $job->scheduleNextCheck();
        $this->repository->save($job);
    }
}
```

- [ ] **Step 6: Create `src/Command/TrackingSchedulerCommand.php`**

```php
<?php
declare(strict_types=1);
namespace App\Command;

use App\Message\TrackingJobMessage;
use App\Repository\TrackingJobRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(name: 'app:tracking:schedule', description: 'Dispatch due tracking jobs to async queue')]
class TrackingSchedulerCommand extends Command
{
    public function __construct(
        private readonly TrackingJobRepository $repository,
        private readonly MessageBusInterface   $bus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Max jobs per run', 50);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io    = new SymfonyStyle($input, $output);
        $limit = (int) $input->getOption('limit');
        $jobs  = $this->repository->findDueJobs($limit);

        foreach ($jobs as $job) {
            $this->bus->dispatch(new TrackingJobMessage($job->getId()));
        }

        $io->success(sprintf('Dispatched %d tracking job(s).', count($jobs)));
        return Command::SUCCESS;
    }
}
```

- [ ] **Step 7: Update `config/packages/messenger.yaml` — add routing**

In the `routing:` section, add:
```yaml
            App\Message\TrackingJobMessage: async
```

Final `routing:` block should look like:
```yaml
        routing:
            Symfony\Component\Notifier\Message\ChatMessage: async
            Symfony\Component\Notifier\Message\SmsMessage: async
            App\Message\TrackingJobMessage: async
```

- [ ] **Step 8: Commit**

```
git add src/Message/ src/MessageHandler/ src/Service/Tracking/TrackingDispatcherService.php src/Service/Tracking/TrackingCallbackService.php src/Command/TrackingSchedulerCommand.php config/packages/messenger.yaml config/services.yaml
git commit -m "feat: add tracking dispatcher, callback service, Messenger message/handler, scheduler command"
```
_(Run in `D:\Projects\make-cargo`)_

---

## Task 4: Master API — TrackingJobController

**Files:**
- Create: `D:\Projects\make-cargo\src\Controller\Http\TrackingJobController.php`

The controller lives at `/api/public/tracking-job`. Security.yaml marks `/api/public` as `PUBLIC_ACCESS`, so we validate `X-Service-Token` manually.

- [ ] **Step 1: Create `src/Controller/Http/TrackingJobController.php`**

```php
<?php
declare(strict_types=1);
namespace App\Controller\Http;

use App\Entity\TrackingJob;
use App\Repository\TrackingJobRepository;
use App\Service\InterServiceTokenService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/public/tracking-job')]
class TrackingJobController extends AbstractController
{
    public function __construct(
        private readonly TrackingJobRepository    $repository,
        private readonly InterServiceTokenService $tokenService,
    ) {}

    #[Route('', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        if (!$this->tokenService->validate($request->headers->get('X-Service-Token', ''))) {
            return $this->json(['error' => 'Invalid service token.'], Response::HTTP_FORBIDDEN);
        }

        $body = json_decode($request->getContent(), true) ?? [];
        $required = ['trackingType', 'trackingRef', 'callbackUrl', 'callbackSecret'];
        foreach ($required as $field) {
            if (empty($body[$field])) {
                return $this->json(['error' => "Field '{$field}' is required."], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        $job = new TrackingJob();
        $job->setTrackingType($body['trackingType']);
        $job->setTrackingRef($body['trackingRef']);
        $job->setCarrierScac($body['carrierScac'] ?? null);
        $job->setCallbackUrl($body['callbackUrl']);
        $job->setCallbackSecret($body['callbackSecret']);
        if (isset($body['checkFrequencyHours'])) {
            $job->setCheckFrequencyHours((int) $body['checkFrequencyHours']);
        }

        $this->repository->save($job);

        return $this->json(['id' => $job->getId()], Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        if (!$this->tokenService->validate($request->headers->get('X-Service-Token', ''))) {
            return $this->json(['error' => 'Invalid service token.'], Response::HTTP_FORBIDDEN);
        }

        $job = $this->repository->find($id);
        if (!$job) {
            throw $this->createNotFoundException();
        }

        $body = json_decode($request->getContent(), true) ?? [];
        if (isset($body['status'])) {
            $job->setStatus($body['status']);
        }
        if (isset($body['checkFrequencyHours'])) {
            $job->setCheckFrequencyHours((int) $body['checkFrequencyHours']);
        }

        $this->repository->save($job);
        return $this->json(['id' => $job->getId(), 'status' => $job->getStatus()]);
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(int $id, Request $request): JsonResponse
    {
        if (!$this->tokenService->validate($request->headers->get('X-Service-Token', ''))) {
            return $this->json(['error' => 'Invalid service token.'], Response::HTTP_FORBIDDEN);
        }

        $job = $this->repository->find($id);
        if (!$job) {
            throw $this->createNotFoundException();
        }

        $this->repository->delete($job);
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
```

- [ ] **Step 2: Commit**

```
git add src/Controller/Http/TrackingJobController.php
git commit -m "feat: add TrackingJobController for client API registration"
```
_(Run in `D:\Projects\make-cargo`)_

---

## Task 5: Client API — Entities + Migrations

**Files:**
- Create: `src/Entity/TrackingRequest.php`
- Create: `src/Entity/TrackingEventRaw.php`
- Create: `src/Entity/CarrierEventMapping.php`
- Create: `src/Repository/TrackingRequestRepository.php`
- Create: `src/Repository/TrackingEventRawRepository.php`
- Create: `src/Repository/CarrierEventMappingRepository.php`
- Create: migrations (mysql + sqlite) for each entity
- Create: serializer_groups YAML for each entity

_(All in `d:\Projects\make-cargo-client`)_

- [ ] **Step 1: Create `src/Entity/TrackingRequest.php`**

```php
<?php
namespace App\Entity;

use App\Misc\Traits\EntityDateTimeAbleTrait;
use App\Repository\TrackingRequestRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TrackingRequestRepository::class)]
#[ORM\HasLifecycleCallbacks]
class TrackingRequest
{
    use EntityDateTimeAbleTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Shipment $shipment = null;

    #[ORM\Column(length: 32)]
    private string $trackingType;

    #[ORM\Column(length: 64)]
    private string $trackingRef;

    #[ORM\Column(length: 8, nullable: true)]
    private ?string $carrierScac = null;

    #[ORM\Column(length: 16)]
    private string $status = 'ACTIVE';

    #[ORM\Column(nullable: true)]
    private ?int $masterJobId = null;

    #[ORM\Column(length: 64)]
    private string $webhookSecret;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $lastCheckedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $lastEventAt = null;

    #[ORM\Column]
    private int $errorCount = 0;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $lastError = null;

    public function getId(): ?int { return $this->id; }
    public function getShipment(): ?Shipment { return $this->shipment; }
    public function setShipment(?Shipment $s): static { $this->shipment = $s; return $this; }
    public function getTrackingType(): string { return $this->trackingType; }
    public function setTrackingType(string $v): static { $this->trackingType = $v; return $this; }
    public function getTrackingRef(): string { return $this->trackingRef; }
    public function setTrackingRef(string $v): static { $this->trackingRef = $v; return $this; }
    public function getCarrierScac(): ?string { return $this->carrierScac; }
    public function setCarrierScac(?string $v): static { $this->carrierScac = $v; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $v): static { $this->status = $v; return $this; }
    public function getMasterJobId(): ?int { return $this->masterJobId; }
    public function setMasterJobId(?int $v): static { $this->masterJobId = $v; return $this; }
    public function getWebhookSecret(): string { return $this->webhookSecret; }
    public function setWebhookSecret(string $v): static { $this->webhookSecret = $v; return $this; }
    public function getLastCheckedAt(): ?\DateTimeInterface { return $this->lastCheckedAt; }
    public function setLastCheckedAt(?\DateTimeInterface $v): static { $this->lastCheckedAt = $v; return $this; }
    public function getLastEventAt(): ?\DateTimeInterface { return $this->lastEventAt; }
    public function setLastEventAt(?\DateTimeInterface $v): static { $this->lastEventAt = $v; return $this; }
    public function getErrorCount(): int { return $this->errorCount; }
    public function setErrorCount(int $v): static { $this->errorCount = $v; return $this; }
    public function getLastError(): ?string { return $this->lastError; }
    public function setLastError(?string $v): static { $this->lastError = $v; return $this; }
}
```

- [ ] **Step 2: Create `src/Entity/TrackingEventRaw.php`**

```php
<?php
namespace App\Entity;

use App\Repository\TrackingEventRawRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TrackingEventRawRepository::class)]
#[ORM\HasLifecycleCallbacks]
class TrackingEventRaw
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?TrackingRequest $trackingRequest = null;

    #[ORM\Column(length: 32)]
    private string $source;

    #[ORM\Column(type: Types::JSON)]
    private array $rawPayload = [];

    #[ORM\Column]
    private bool $isProcessed = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $processedAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $error = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $receivedAt = null;

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->receivedAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getTrackingRequest(): ?TrackingRequest { return $this->trackingRequest; }
    public function setTrackingRequest(?TrackingRequest $v): static { $this->trackingRequest = $v; return $this; }
    public function getSource(): string { return $this->source; }
    public function setSource(string $v): static { $this->source = $v; return $this; }
    public function getRawPayload(): array { return $this->rawPayload; }
    public function setRawPayload(array $v): static { $this->rawPayload = $v; return $this; }
    public function isProcessed(): bool { return $this->isProcessed; }
    public function setIsProcessed(bool $v): static { $this->isProcessed = $v; return $this; }
    public function getProcessedAt(): ?\DateTimeInterface { return $this->processedAt; }
    public function setProcessedAt(?\DateTimeInterface $v): static { $this->processedAt = $v; return $this; }
    public function getError(): ?string { return $this->error; }
    public function setError(?string $v): static { $this->error = $v; return $this; }
    public function getReceivedAt(): ?\DateTimeInterface { return $this->receivedAt; }
}
```

- [ ] **Step 3: Create `src/Entity/CarrierEventMapping.php`**

```php
<?php
namespace App\Entity;

use App\Misc\Enum\MilestoneCode;
use App\Misc\Traits\EntityDateTimeAbleTrait;
use App\Repository\CarrierEventMappingRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CarrierEventMappingRepository::class)]
#[ORM\UniqueConstraint(name: 'UQ_carrier_event', columns: ['carrier_scac', 'carrier_event_code'])]
#[ORM\HasLifecycleCallbacks]
class CarrierEventMapping
{
    use EntityDateTimeAbleTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 8)]
    private string $carrierScac;

    #[ORM\Column(length: 64)]
    private string $carrierEventCode;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $carrierEventDescription = null;

    #[ORM\Column(length: 32, enumType: MilestoneCode::class, nullable: true)]
    private ?MilestoneCode $milestoneCode = null;

    #[ORM\Column(length: 8)]
    private string $confidence = 'HIGH';

    public function getId(): ?int { return $this->id; }
    public function getCarrierScac(): string { return $this->carrierScac; }
    public function setCarrierScac(string $v): static { $this->carrierScac = $v; return $this; }
    public function getCarrierEventCode(): string { return $this->carrierEventCode; }
    public function setCarrierEventCode(string $v): static { $this->carrierEventCode = $v; return $this; }
    public function getCarrierEventDescription(): ?string { return $this->carrierEventDescription; }
    public function setCarrierEventDescription(?string $v): static { $this->carrierEventDescription = $v; return $this; }
    public function getMilestoneCode(): ?MilestoneCode { return $this->milestoneCode; }
    public function setMilestoneCode(?MilestoneCode $v): static { $this->milestoneCode = $v; return $this; }
    public function getConfidence(): string { return $this->confidence; }
    public function setConfidence(string $v): static { $this->confidence = $v; return $this; }
}
```

- [ ] **Step 4: Create repositories**

`src/Repository/TrackingRequestRepository.php`:
```php
<?php
namespace App\Repository;

use App\Entity\TrackingRequest;

class TrackingRequestRepository extends BaseRepository
{
    /** @return TrackingRequest[] */
    public function findByShipment(int $shipmentId): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.shipment = :sid')
            ->setParameter('sid', $shipmentId)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
```

`src/Repository/TrackingEventRawRepository.php`:
```php
<?php
namespace App\Repository;

use App\Entity\TrackingEventRaw;

class TrackingEventRawRepository extends BaseRepository
{
    /** @return TrackingEventRaw[] */
    public function findByTrackingRequest(int $requestId): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.trackingRequest = :rid')
            ->setParameter('rid', $requestId)
            ->orderBy('e.receivedAt', 'DESC')
            ->setMaxResults(100)
            ->getQuery()
            ->getResult();
    }
}
```

`src/Repository/CarrierEventMappingRepository.php`:
```php
<?php
namespace App\Repository;

use App\Entity\CarrierEventMapping;

class CarrierEventMappingRepository extends BaseRepository
{
    public function findByCarrierAndCode(string $carrierScac, string $eventCode): ?CarrierEventMapping
    {
        return $this->findOneBy([
            'carrierScac'      => $carrierScac,
            'carrierEventCode' => $eventCode,
        ]);
    }
}
```

- [ ] **Step 5: Create MySQL migration for tracking_request — `migrations/mysql/Version20260624110000.php`**

```php
<?php
declare(strict_types=1);
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260624110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create tracking_request table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE tracking_request (id INT NOT NULL AUTO_INCREMENT, shipment_id INT NOT NULL, tracking_type VARCHAR(32) NOT NULL, tracking_ref VARCHAR(64) NOT NULL, carrier_scac VARCHAR(8) DEFAULT NULL, status VARCHAR(16) NOT NULL DEFAULT \'ACTIVE\', master_job_id INT DEFAULT NULL, webhook_secret VARCHAR(64) NOT NULL, last_checked_at DATETIME DEFAULT NULL, last_event_at DATETIME DEFAULT NULL, error_count INT NOT NULL DEFAULT 0, last_error LONGTEXT DEFAULT NULL, created_date DATETIME NOT NULL, updated_date DATETIME DEFAULT NULL, CONSTRAINT FK_tracking_request_shipment FOREIGN KEY (shipment_id) REFERENCES shipment (id) ON DELETE CASCADE, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE INDEX IDX_tracking_request_shipment ON tracking_request (shipment_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE tracking_request');
    }
}
```

- [ ] **Step 6: Create SQLite migration for tracking_request — `migrations/sqlite/Version20260624110000.php`**

```php
<?php
declare(strict_types=1);
namespace SqlEngineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260624110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create tracking_request table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE tracking_request (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, shipment_id INTEGER NOT NULL, tracking_type VARCHAR(32) NOT NULL, tracking_ref VARCHAR(64) NOT NULL, carrier_scac VARCHAR(8) DEFAULT NULL, status VARCHAR(16) NOT NULL DEFAULT \'ACTIVE\', master_job_id INTEGER DEFAULT NULL, webhook_secret VARCHAR(64) NOT NULL, last_checked_at DATETIME DEFAULT NULL, last_event_at DATETIME DEFAULT NULL, error_count INTEGER NOT NULL DEFAULT 0, last_error CLOB DEFAULT NULL, created_date DATETIME NOT NULL, updated_date DATETIME DEFAULT NULL, CONSTRAINT FK_tracking_request_shipment FOREIGN KEY (shipment_id) REFERENCES shipment (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_tracking_request_shipment ON tracking_request (shipment_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE tracking_request');
    }
}
```

- [ ] **Step 7: Create MySQL migration for tracking_event_raw — `migrations/mysql/Version20260624120000.php`**

```php
<?php
declare(strict_types=1);
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260624120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create tracking_event_raw table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE tracking_event_raw (id INT NOT NULL AUTO_INCREMENT, tracking_request_id INT NOT NULL, source VARCHAR(32) NOT NULL, raw_payload JSON NOT NULL, is_processed TINYINT(1) NOT NULL DEFAULT 0, processed_at DATETIME DEFAULT NULL, error LONGTEXT DEFAULT NULL, received_at DATETIME NOT NULL, CONSTRAINT FK_tracking_event_raw_request FOREIGN KEY (tracking_request_id) REFERENCES tracking_request (id) ON DELETE CASCADE, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE INDEX IDX_tracking_event_raw_request ON tracking_event_raw (tracking_request_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE tracking_event_raw');
    }
}
```

- [ ] **Step 8: Create SQLite migration for tracking_event_raw — `migrations/sqlite/Version20260624120000.php`**

```php
<?php
declare(strict_types=1);
namespace SqlEngineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260624120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create tracking_event_raw table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE tracking_event_raw (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, tracking_request_id INTEGER NOT NULL, source VARCHAR(32) NOT NULL, raw_payload CLOB NOT NULL, is_processed INTEGER NOT NULL DEFAULT 0, processed_at DATETIME DEFAULT NULL, error CLOB DEFAULT NULL, received_at DATETIME NOT NULL, CONSTRAINT FK_tracking_event_raw_request FOREIGN KEY (tracking_request_id) REFERENCES tracking_request (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_tracking_event_raw_request ON tracking_event_raw (tracking_request_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE tracking_event_raw');
    }
}
```

- [ ] **Step 9: Create MySQL migration for carrier_event_mapping — `migrations/mysql/Version20260624130000.php`**

```php
<?php
declare(strict_types=1);
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260624130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create carrier_event_mapping table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE carrier_event_mapping (id INT NOT NULL AUTO_INCREMENT, carrier_scac VARCHAR(8) NOT NULL, carrier_event_code VARCHAR(64) NOT NULL, carrier_event_description VARCHAR(255) DEFAULT NULL, milestone_code VARCHAR(32) DEFAULT NULL, confidence VARCHAR(8) NOT NULL DEFAULT \'HIGH\', created_date DATETIME NOT NULL, updated_date DATETIME DEFAULT NULL, PRIMARY KEY (id), UNIQUE INDEX UQ_carrier_event (carrier_scac, carrier_event_code)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE carrier_event_mapping');
    }
}
```

- [ ] **Step 10: Create SQLite migration for carrier_event_mapping — `migrations/sqlite/Version20260624130000.php`**

```php
<?php
declare(strict_types=1);
namespace SqlEngineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260624130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create carrier_event_mapping table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE carrier_event_mapping (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, carrier_scac VARCHAR(8) NOT NULL, carrier_event_code VARCHAR(64) NOT NULL, carrier_event_description VARCHAR(255) DEFAULT NULL, milestone_code VARCHAR(32) DEFAULT NULL, confidence VARCHAR(8) NOT NULL DEFAULT \'HIGH\', created_date DATETIME NOT NULL, updated_date DATETIME DEFAULT NULL, CONSTRAINT UQ_carrier_event UNIQUE (carrier_scac, carrier_event_code))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE carrier_event_mapping');
    }
}
```

- [ ] **Step 11: Create serializer group YAMLs**

`config/serializer_groups/TrackingRequest.yaml`:
```yaml
App\Entity\TrackingRequest:

    list:
        - id
        - trackingType
        - trackingRef
        - carrierScac
        - status
        - masterJobId
        - lastCheckedAt
        - lastEventAt
        - errorCount
        - lastError
        - createdAt
        - updatedAt

    detail:
        - id
        - trackingType
        - trackingRef
        - carrierScac
        - status
        - masterJobId
        - lastCheckedAt
        - lastEventAt
        - errorCount
        - lastError
        - createdAt
        - updatedAt
```

`config/serializer_groups/TrackingEventRaw.yaml`:
```yaml
App\Entity\TrackingEventRaw:

    list:
        - id
        - source
        - rawPayload
        - isProcessed
        - processedAt
        - error
        - receivedAt

    detail:
        - id
        - source
        - rawPayload
        - isProcessed
        - processedAt
        - error
        - receivedAt
```

`config/serializer_groups/CarrierEventMapping.yaml`:
```yaml
App\Entity\CarrierEventMapping:

    list:
        - id
        - carrierScac
        - carrierEventCode
        - carrierEventDescription
        - milestoneCode
        - confidence
        - createdAt
        - updatedAt

    detail:
        - id
        - carrierScac
        - carrierEventCode
        - carrierEventDescription
        - milestoneCode
        - confidence
        - createdAt
        - updatedAt
```

- [ ] **Step 12: Commit**

```
git add src/Entity/TrackingRequest.php src/Entity/TrackingEventRaw.php src/Entity/CarrierEventMapping.php
git add src/Repository/TrackingRequestRepository.php src/Repository/TrackingEventRawRepository.php src/Repository/CarrierEventMappingRepository.php
git add migrations/mysql/Version20260624110000.php migrations/sqlite/Version20260624110000.php
git add migrations/mysql/Version20260624120000.php migrations/sqlite/Version20260624120000.php
git add migrations/mysql/Version20260624130000.php migrations/sqlite/Version20260624130000.php
git add config/serializer_groups/TrackingRequest.yaml config/serializer_groups/TrackingEventRaw.yaml config/serializer_groups/CarrierEventMapping.yaml
git commit -m "feat: add TrackingRequest, TrackingEventRaw, CarrierEventMapping entities and migrations"
```
_(Run in `d:\Projects\make-cargo-client`)_

---

## Task 6: Client API — Services + services.yaml

**Files:**
- Create: `src/Service/TrackingRequestService.php`
- Create: `src/Service/TrackingEventRawService.php`
- Create: `src/Service/CarrierEventMappingService.php`
- Create: `src/Service/TrackingMilestoneWriterService.php`
- Modify: `config/services.yaml`

- [ ] **Step 1: Create `src/Service/TrackingRequestService.php`**

```php
<?php
namespace App\Service;

use App\Entity\Shipment;
use App\Entity\TrackingRequest;
use App\Repository\TrackingRequestRepository;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TrackingRequestService extends BaseService
{
    public function __construct(
        protected BaseService $baseService,
        public TrackingRequestRepository $repository,
        private readonly HttpClientInterface $httpClient,
        private readonly ParameterBagInterface $params,
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
        $masterUrl = rtrim($this->params->get('master_api_url'), '/');
        $callbackUrl = rtrim($this->params->get('app_base_url'), '/') . '/tracking-webhook/' . $request->getId();

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
            // Master API is optional; tracking will remain un-polled
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

    public function delete(TrackingRequest $request): void
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
```

**Note:** `app_base_url` must be added as a parameter in `config/services.yaml`:
```yaml
  app_base_url: '%env(resolve:APP_BASE_URL)%'
```

- [ ] **Step 2: Create `src/Service/TrackingEventRawService.php`**

```php
<?php
namespace App\Service;

use App\Repository\TrackingEventRawRepository;

class TrackingEventRawService extends BaseService
{
    public function __construct(
        protected BaseService $baseService,
        public TrackingEventRawRepository $repository,
    ) {
        $this->reflectFromParent($baseService);
    }
}
```

- [ ] **Step 3: Create `src/Service/CarrierEventMappingService.php`**

```php
<?php
namespace App\Service;

use App\Repository\CarrierEventMappingRepository;

class CarrierEventMappingService extends BaseService
{
    public function __construct(
        protected BaseService $baseService,
        public CarrierEventMappingRepository $repository,
    ) {
        $this->reflectFromParent($baseService);
    }
}
```

- [ ] **Step 4: Create `src/Service/TrackingMilestoneWriterService.php`**

```php
<?php
namespace App\Service;

use App\Entity\ShipmentMilestone;
use App\Entity\TrackingRequest;
use App\Misc\Enum\MilestoneCode;
use App\Repository\CarrierEventMappingRepository;
use App\Repository\ShipmentMilestoneRepository;

class TrackingMilestoneWriterService
{
    public function __construct(
        private readonly CarrierEventMappingRepository $mappingRepository,
        private readonly ShipmentMilestoneRepository   $milestoneRepository,
    ) {}

    /**
     * @param array<int, array{eventCode: string, eventDescription: string, eventDate: string, location: string|null}> $events
     */
    public function writeEvents(TrackingRequest $request, string $source, array $events): void
    {
        $shipment = $request->getShipment();
        $shipmentId = $shipment->getId();

        foreach ($events as $event) {
            $code = $event['eventCode'] ?? '';
            $mapping = $this->mappingRepository->findByCarrierAndCode($source, $code);
            if (!$mapping || !$mapping->getMilestoneCode()) {
                continue;
            }

            $milestoneCode = $mapping->getMilestoneCode();
            $existing = $this->milestoneRepository->findByShipmentAndCode($shipmentId, $milestoneCode);

            if ($existing && $existing->getSource() === 'MANUAL') {
                continue;
            }

            if (!$existing) {
                $existing = (new ShipmentMilestone())
                    ->setShipment($shipment)
                    ->setMilestoneCode($milestoneCode);
            }

            $eventDate = isset($event['eventDate'])
                ? new \DateTime($event['eventDate'])
                : new \DateTime();

            $existing->setActualDate($eventDate);
            $existing->setSource('AUTOMATED');
            $existing->recalculateException();

            $this->milestoneRepository->save($existing);
        }
    }
}
```

- [ ] **Step 5: Update `config/services.yaml`**

Add the `app_base_url` parameter under `parameters:`:
```yaml
  app_base_url: '%env(resolve:APP_BASE_URL)%'
```

Add 4 new services to `app.auto_service_locator`:
```yaml
                App\Service\TrackingRequestService: '@App\Service\TrackingRequestService'
                App\Service\TrackingEventRawService: '@App\Service\TrackingEventRawService'
                App\Service\CarrierEventMappingService: '@App\Service\CarrierEventMappingService'
```

Also add `TrackingMilestoneWriterService` as a standalone service (it doesn't extend `BaseService`):
It will be auto-discovered by the `App\:` resource block — no manual registration needed.

- [ ] **Step 6: Commit**

```
git add src/Service/TrackingRequestService.php src/Service/TrackingEventRawService.php src/Service/CarrierEventMappingService.php src/Service/TrackingMilestoneWriterService.php config/services.yaml
git commit -m "feat: add tracking services and register in service locator"
```
_(Run in `d:\Projects\make-cargo-client`)_

---

## Task 7: Client API — Controllers

**Files:**
- Create: `src/Controller/Api/TrackingRequestController.php`
- Create: `src/Controller/Api/CarrierEventMappingController.php`
- Create: `src/Controller/Api/TrackingWebhookController.php`

- [ ] **Step 1: Create `src/Controller/Api/TrackingRequestController.php`**

Nested under `/shipment/{shipmentId}/tracking-requests`. Uses `CrudController` traits for list/get/delete, with custom POST logic to call `TrackingRequestService::createForShipment()`.

```php
<?php
namespace App\Controller\Api;

use App\Entity\TrackingRequest;
use App\Misc\Trait\Controller\DeleteActionTrait;
use App\Misc\Trait\Controller\GetActionTrait;
use App\Repository\ShipmentRepository;
use App\Repository\TrackingRequestRepository;
use App\Service\TrackingRequestService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/shipment/{shipmentId}/tracking-requests')]
#[IsGranted('ROLE_USER')]
class TrackingRequestController extends CrudController
{
    use GetActionTrait;

    #[Route('', methods: ['GET'])]
    public function list(int $shipmentId, TrackingRequestRepository $repo): JsonResponse
    {
        $items = $repo->findByShipment($shipmentId);
        return $this->json(array_map(fn($r) => $this->serializeOne($r, 'list'), $items));
    }

    #[Route('', methods: ['POST'])]
    public function create(int $shipmentId, Request $request, ShipmentRepository $shipmentRepo, TrackingRequestService $service): JsonResponse
    {
        $shipment = $shipmentRepo->find($shipmentId);
        if (!$shipment) throw $this->createNotFoundException();

        $body = json_decode($request->getContent(), true) ?? [];
        if (empty($body['trackingType']) || empty($body['trackingRef'])) {
            return $this->json(['error' => 'trackingType and trackingRef are required.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $req = $service->createForShipment($shipment, $body);
        return $this->json($this->serializeOne($req, 'detail'), Response::HTTP_CREATED);
    }

    #[Route('/{id}/pause', methods: ['PATCH'])]
    public function pause(int $shipmentId, int $id, TrackingRequestRepository $repo, TrackingRequestService $service): JsonResponse
    {
        $req = $repo->find($id);
        if (!$req || $req->getShipment()->getId() !== $shipmentId) throw $this->createNotFoundException();
        $service->updateStatus($req, 'PAUSED');
        return $this->json($this->serializeOne($req, 'detail'));
    }

    #[Route('/{id}/resume', methods: ['PATCH'])]
    public function resume(int $shipmentId, int $id, TrackingRequestRepository $repo, TrackingRequestService $service): JsonResponse
    {
        $req = $repo->find($id);
        if (!$req || $req->getShipment()->getId() !== $shipmentId) throw $this->createNotFoundException();
        $service->updateStatus($req, 'ACTIVE');
        return $this->json($this->serializeOne($req, 'detail'));
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(int $shipmentId, int $id, TrackingRequestRepository $repo, TrackingRequestService $service): JsonResponse
    {
        $req = $repo->find($id);
        if (!$req || $req->getShipment()->getId() !== $shipmentId) throw $this->createNotFoundException();
        $service->delete($req);
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/events', methods: ['GET'])]
    public function events(int $shipmentId, int $id, TrackingRequestRepository $repo, \App\Repository\TrackingEventRawRepository $eventRepo): JsonResponse
    {
        $req = $repo->find($id);
        if (!$req || $req->getShipment()->getId() !== $shipmentId) throw $this->createNotFoundException();
        $events = $eventRepo->findByTrackingRequest($id);
        return $this->json(array_map(fn($e) => $this->serializeOne($e, 'list'), $events));
    }

    private function serializeOne(object $entity, string $group): array
    {
        return $this->container->get(\App\Service\BaseService::class)->serialize($entity, $group);
    }
}
```

- [ ] **Step 2: Create `src/Controller/Api/CarrierEventMappingController.php`**

Standard CRUD — extends `CrudController` with all four traits.

```php
<?php
namespace App\Controller\Api;

use App\Misc\Trait\Controller\DeleteActionTrait;
use App\Misc\Trait\Controller\GetActionTrait;
use App\Misc\Trait\Controller\PostActionTrait;
use App\Misc\Trait\Controller\PutActionTrait;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/carrier-event-mapping')]
#[IsGranted('ROLE_USER')]
class CarrierEventMappingController extends CrudController
{
    use GetActionTrait;
    use PostActionTrait;
    use PutActionTrait;
    use DeleteActionTrait;
}
```

- [ ] **Step 3: Create `src/Controller/Api/TrackingWebhookController.php`**

No `#[IsGranted]` — it's a webhook endpoint. Validates the per-request secret.

```php
<?php
namespace App\Controller\Api;

use App\Entity\TrackingEventRaw;
use App\Repository\TrackingEventRawRepository;
use App\Repository\TrackingRequestRepository;
use App\Service\TrackingMilestoneWriterService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class TrackingWebhookController extends AbstractController
{
    public function __construct(
        private readonly TrackingRequestRepository    $requestRepository,
        private readonly TrackingEventRawRepository   $eventRepository,
        private readonly TrackingMilestoneWriterService $milestoneWriter,
    ) {}

    #[Route('/tracking-webhook/{trackingRequestId}', methods: ['POST'])]
    public function receive(int $trackingRequestId, Request $request): JsonResponse
    {
        $trackingRequest = $this->requestRepository->find($trackingRequestId);
        if (!$trackingRequest) {
            return $this->json(['error' => 'Not found.'], Response::HTTP_NOT_FOUND);
        }

        $secret = $request->headers->get('X-Tracking-Secret', '');
        if (!hash_equals($trackingRequest->getWebhookSecret(), $secret)) {
            return $this->json(['error' => 'Invalid secret.'], Response::HTTP_FORBIDDEN);
        }

        $body = json_decode($request->getContent(), true) ?? [];
        $source = $body['source'] ?? 'UNKNOWN';
        $events = $body['events'] ?? [];

        $raw = new TrackingEventRaw();
        $raw->setTrackingRequest($trackingRequest);
        $raw->setSource($source);
        $raw->setRawPayload($body);

        try {
            $this->milestoneWriter->writeEvents($trackingRequest, $source, $events);
            $raw->setIsProcessed(true);
            $raw->setProcessedAt(new \DateTime());

            $trackingRequest->setLastCheckedAt(new \DateTime());
            if (!empty($events)) {
                $trackingRequest->setLastEventAt(new \DateTime());
            }
            $this->requestRepository->save($trackingRequest);
        } catch (\Throwable $e) {
            $raw->setError($e->getMessage());
        }

        $this->eventRepository->save($raw);

        return $this->json(['received' => true]);
    }
}
```

- [ ] **Step 4: Add `save()` and `delete()` methods to repositories that don't have them**

Add to `TrackingRequestRepository`:
```php
    public function save(TrackingRequest $entity): TrackingRequest
    {
        $em = $this->getEntityManager();
        $em->persist($entity);
        $em->flush();
        return $entity;
    }

    public function delete(TrackingRequest $entity): void
    {
        $em = $this->getEntityManager();
        $em->remove($entity);
        $em->flush();
    }
```

Add to `TrackingEventRawRepository`:
```php
    public function save(TrackingEventRaw $entity): TrackingEventRaw
    {
        $em = $this->getEntityManager();
        $em->persist($entity);
        $em->flush();
        return $entity;
    }
```

- [ ] **Step 5: Commit**

```
git add src/Controller/Api/TrackingRequestController.php src/Controller/Api/CarrierEventMappingController.php src/Controller/Api/TrackingWebhookController.php src/Repository/TrackingRequestRepository.php src/Repository/TrackingEventRawRepository.php
git commit -m "feat: add tracking request, carrier mapping, and webhook controllers"
```
_(Run in `d:\Projects\make-cargo-client`)_

---

## Task 8: Client BO — Library (Carrier Event Mapping Pages)

_(All in `d:\Projects\make-cargo-client-bo`)_

**Files:**
- Create: `src/services/library/CarrierEventMappingService.js`
- Create: `src/config/forms/library/CarrierEventMapping.js`
- Create: `src/config/tables/library/CarrierEventMapping.js`
- Create: `src/views/library/CarrierEventMappingForm.vue`
- Create: `src/pages/library/carrier-event-mapping.vue`
- Modify: `src/config/navigation/index.js`

- [ ] **Step 1: Create `src/services/library/CarrierEventMappingService.js`**

```js
import CommonService from '@/services/CommonService'

const BASE_URI = 'carrier-event-mapping'

export default {
  list(params = '') {
    return $api(`${BASE_URI}?${params}`)
  },
  get(id) {
    return $api(`${BASE_URI}/${id}`)
  },
  add(entity) {
    return $api(BASE_URI, { method: 'POST', body: CommonService.formData(entity), loading: true })
  },
  update(entity) {
    return $api(BASE_URI, { method: 'PUT', body: CommonService.formData(entity), loading: true })
  },
  delete(id) {
    return $api(`${BASE_URI}/${id}`, { method: 'DELETE', loading: true })
  },
}
```

- [ ] **Step 2: Create `src/config/forms/library/CarrierEventMapping.js`**

```js
export function layout() {
  return [
    { cols: 12, md: 4, field: 'carrierScac',             label: $gettext('Carrier SCAC'),             type: 'text',   required: true },
    { cols: 12, md: 4, field: 'carrierEventCode',         label: $gettext('Carrier Event Code'),       type: 'text',   required: true },
    { cols: 12, md: 4, field: 'confidence',               label: $gettext('Confidence'),               type: 'select', required: true,
      items: ['HIGH', 'MEDIUM', 'LOW'] },
    { cols: 12, md: 8, field: 'carrierEventDescription',  label: $gettext('Carrier Event Description'), type: 'text' },
    { cols: 12, md: 4, field: 'milestoneCode',            label: $gettext('Milestone Code'),           type: 'text' },
  ]
}
```

- [ ] **Step 3: Create `src/config/tables/library/CarrierEventMapping.js`**

```js
export const filterConfigs = []

export function headers() {
  return [
    { title: $gettext('ID'),          key: 'id',                     sortable: true },
    { title: $gettext('Carrier SCAC'), key: 'carrierScac',           sortable: true },
    { title: $gettext('Event Code'),   key: 'carrierEventCode',      sortable: true },
    { title: $gettext('Description'),  key: 'carrierEventDescription', sortable: false },
    { title: $gettext('Milestone'),    key: 'milestoneCode',          sortable: true },
    { title: $gettext('Confidence'),   key: 'confidence',             sortable: true },
    { title: '',                        key: 'action',                sortable: false },
  ]
}
```

- [ ] **Step 4: Create `src/views/library/CarrierEventMappingForm.vue`**

```vue
<script setup>
import { layout } from '@/config/forms/library/CarrierEventMapping'
import EntityService from '@/services/library/CarrierEventMappingService'

const emit = defineEmits(['entitySubmitted'])
const form = ref(null)

async function setEntity(entity = null) {
  form.value.open(entity ?? {
    carrierScac: '',
    carrierEventCode: '',
    carrierEventDescription: '',
    milestoneCode: null,
    confidence: 'HIGH',
  })
}

defineExpose({ setEntity })
</script>
<template>
  <AppForm
    ref="form"
    :layout="layout()"
    :apiService="EntityService"
    :title="$gettext('Carrier Event Mapping')"
    @entitySubmitted="$emit('entitySubmitted')"
  />
</template>
```

- [ ] **Step 5: Create `src/pages/library/carrier-event-mapping.vue`**

```vue
<script setup>
import { filterConfigs, headers } from '@/config/tables/library/CarrierEventMapping'
import EntityService from '@/services/library/CarrierEventMappingService'
import CarrierEventMappingForm from '@/views/library/CarrierEventMappingForm.vue'

definePage({ meta: { action: 'GET', subject: 'CarrierEventMapping' } })

const table = ref(null)
const form = ref(null)
const buttons = computed(() => [{ text: $gettext('Add Mapping'), func: form.value?.setEntity }])

async function editEntity(id) {
  const entity = await EntityService.get(id)
  form.value.setEntity(entity)
}
</script>
<template>
  <AppTable
    :headers="headers()"
    :buttons="buttons"
    :filterConfigs="filterConfigs"
    :apiService="EntityService"
    ref="table"
    :pageTitle="$gettext('Carrier Event Mappings')"
  >
    <template #action="{ item }">
      <v-btn @click="editEntity(item.id)" :title="$gettext('edit')" class="grey--text mx-0" variant="text" size="x-small">
        <VIcon icon="tabler-pencil" size="18"/>
      </v-btn>
      <SubmitBtn
        @click="table.handleDelete(item, $refs['delete-' + item.id])"
        :title="$gettext('delete')"
        class="grey--text mx-0 ml-n2" variant="text"
        :autoQueue="false"
        :ref="'delete-' + item.id"
        size="x-small"
      >
        <VIcon icon="tabler-trash" size="18"/>
      </SubmitBtn>
    </template>
  </AppTable>
  <CarrierEventMappingForm ref="form" @entitySubmitted="$refs.table.fetchData()" />
</template>
```

- [ ] **Step 6: Add Carrier Event Mappings to `src/config/navigation/index.js`**

After the `HS Restrictions` entry (line 273), add:
```js
      {
        title: $gettext('Carrier Event Mappings'),
        to: { name: 'library-carrier-event-mapping' },
        subject: 'CarrierEventMapping',
        action: 'GET'
      }
```

- [ ] **Step 7: Commit**

```
git add src/services/library/CarrierEventMappingService.js src/config/forms/library/CarrierEventMapping.js src/config/tables/library/CarrierEventMapping.js src/views/library/CarrierEventMappingForm.vue src/pages/library/carrier-event-mapping.vue src/config/navigation/index.js
git commit -m "feat: add Carrier Event Mapping library pages and navigation"
```
_(Run in `d:\Projects\make-cargo-client-bo`)_

---

## Task 9: Client BO — Shipment Tracking Panels

_(All in `d:\Projects\make-cargo-client-bo`)_

**Files:**
- Create: `src/services/TrackingRequestService.js`
- Create: `src/config/tables/TrackingRequest.js`
- Create: `src/config/tables/TrackingEventRaw.js`
- Create: `src/views/shipment/ShipmentTrackingSubscriptions.vue`
- Create: `src/views/shipment/ShipmentTrackingEvents.vue`
- Modify: `src/views/shipment/ShipmentTracking.vue`

- [ ] **Step 1: Create `src/services/TrackingRequestService.js`**

```js
const BASE_URI = (shipmentId) => `shipment/${shipmentId}/tracking-requests`

export default {
  list(shipmentId) {
    return $api(BASE_URI(shipmentId))
  },
  add(shipmentId, payload) {
    return $api(BASE_URI(shipmentId), { method: 'POST', body: payload, loading: true })
  },
  pause(shipmentId, id) {
    return $api(`${BASE_URI(shipmentId)}/${id}/pause`, { method: 'PATCH', loading: true })
  },
  resume(shipmentId, id) {
    return $api(`${BASE_URI(shipmentId)}/${id}/resume`, { method: 'PATCH', loading: true })
  },
  delete(shipmentId, id) {
    return $api(`${BASE_URI(shipmentId)}/${id}`, { method: 'DELETE', loading: true })
  },
  events(shipmentId, id) {
    return $api(`${BASE_URI(shipmentId)}/${id}/events`)
  },
}
```

- [ ] **Step 2: Create `src/config/tables/TrackingRequest.js`**

```js
export function headers() {
  return [
    { title: $gettext('Type'),         key: 'trackingType',  sortable: false },
    { title: $gettext('Reference'),    key: 'trackingRef',   sortable: false },
    { title: $gettext('Carrier'),      key: 'carrierScac',   sortable: false },
    { title: $gettext('Status'),       key: 'status',        sortable: false },
    { title: $gettext('Last Event'),   key: 'lastEventAt',   sortable: false },
    { title: $gettext('Last Checked'), key: 'lastCheckedAt', sortable: false },
    { title: '',                        key: 'action',        sortable: false },
  ]
}
```

- [ ] **Step 3: Create `src/config/tables/TrackingEventRaw.js`**

```js
export function headers() {
  return [
    { title: $gettext('Received'),    key: 'receivedAt',   sortable: false },
    { title: $gettext('Source'),      key: 'source',       sortable: false },
    { title: $gettext('Processed'),   key: 'isProcessed',  sortable: false },
    { title: $gettext('Error'),       key: 'error',        sortable: false },
  ]
}
```

- [ ] **Step 4: Create `src/views/shipment/ShipmentTrackingSubscriptions.vue`**

```vue
<script setup>
import TrackingRequestService from '@/services/TrackingRequestService'
import { headers } from '@/config/tables/TrackingRequest'

const props = defineProps({ shipment: { type: Object, required: true } })

const requests = ref([])
const loading = ref(false)
const saving = ref(false)
const form = ref({ trackingType: 'CONTAINER', trackingRef: '', carrierScac: '' })
const expandedRow = ref(null)
const events = ref([])
const eventsLoading = ref(false)

const trackingTypes = ['CONTAINER', 'MBL', 'FLIGHT']

async function load() {
  loading.value = true
  requests.value = await TrackingRequestService.list(props.shipment.id) ?? []
  loading.value = false
}

async function add() {
  saving.value = true
  const created = await TrackingRequestService.add(props.shipment.id, { ...form.value })
  if (created) {
    form.value = { trackingType: 'CONTAINER', trackingRef: '', carrierScac: '' }
    await load()
  }
  saving.value = false
}

async function toggleStatus(req) {
  if (req.status === 'ACTIVE') {
    await TrackingRequestService.pause(props.shipment.id, req.id)
  } else {
    await TrackingRequestService.resume(props.shipment.id, req.id)
  }
  await load()
}

async function remove(req) {
  await TrackingRequestService.delete(props.shipment.id, req.id)
  await load()
}

async function showEvents(req) {
  if (expandedRow.value === req.id) {
    expandedRow.value = null
    events.value = []
    return
  }
  expandedRow.value = req.id
  eventsLoading.value = true
  events.value = await TrackingRequestService.events(props.shipment.id, req.id) ?? []
  eventsLoading.value = false
}

onMounted(load)
</script>
<template>
  <VCard :elevation="0" :loading="loading" class="mt-4">
    <VCardText>
      <div class="text-subtitle-1 mb-4">{{ $gettext('Add Tracking Subscription') }}</div>
      <VRow>
        <VCol cols="12" md="3">
          <VSelect v-model="form.trackingType" :items="trackingTypes" :label="$gettext('Type')" density="compact" />
        </VCol>
        <VCol cols="12" md="4">
          <VTextField v-model="form.trackingRef" :label="$gettext('Reference (container/MBL/flight)')" density="compact" />
        </VCol>
        <VCol cols="12" md="2">
          <VTextField v-model="form.carrierScac" :label="$gettext('Carrier SCAC')" density="compact" />
        </VCol>
        <VCol cols="12" md="3" class="d-flex align-center">
          <SubmitBtn :loading="saving" @click="add" :disabled="!form.trackingRef">
            {{ $gettext('Subscribe') }}
          </SubmitBtn>
        </VCol>
      </VRow>

      <VDivider class="my-4" />

      <VTable density="compact">
        <thead>
          <tr>
            <th v-for="h in headers()" :key="h.key">{{ h.title }}</th>
          </tr>
        </thead>
        <tbody>
          <template v-for="req in requests" :key="req.id">
            <tr>
              <td>{{ req.trackingType }}</td>
              <td>{{ req.trackingRef }}</td>
              <td>{{ req.carrierScac ?? '—' }}</td>
              <td>
                <VChip :color="req.status === 'ACTIVE' ? 'success' : req.status === 'FAILED' ? 'error' : 'warning'" size="small">
                  {{ req.status }}
                </VChip>
              </td>
              <td>{{ req.lastEventAt ? new Date(req.lastEventAt).toLocaleString() : '—' }}</td>
              <td>{{ req.lastCheckedAt ? new Date(req.lastCheckedAt).toLocaleString() : '—' }}</td>
              <td>
                <VBtn size="x-small" variant="text" @click="showEvents(req)" :title="$gettext('events')">
                  <VIcon :icon="expandedRow === req.id ? 'tabler-chevron-up' : 'tabler-list-details'" size="18" />
                </VBtn>
                <VBtn size="x-small" variant="text" @click="toggleStatus(req)" :title="req.status === 'ACTIVE' ? $gettext('pause') : $gettext('resume')">
                  <VIcon :icon="req.status === 'ACTIVE' ? 'tabler-player-pause' : 'tabler-player-play'" size="18" />
                </VBtn>
                <VBtn size="x-small" variant="text" color="error" @click="remove(req)" :title="$gettext('delete')">
                  <VIcon icon="tabler-trash" size="18" />
                </VBtn>
              </td>
            </tr>
            <tr v-if="expandedRow === req.id">
              <td colspan="7" class="pa-0">
                <VCard :elevation="0" class="ma-2">
                  <VCardText>
                    <div class="text-caption text-medium-emphasis mb-2">{{ $gettext('Raw Events') }}</div>
                    <VProgressLinear v-if="eventsLoading" indeterminate />
                    <VTable v-else density="compact">
                      <thead>
                        <tr>
                          <th>{{ $gettext('Received') }}</th>
                          <th>{{ $gettext('Source') }}</th>
                          <th>{{ $gettext('Processed') }}</th>
                          <th>{{ $gettext('Error') }}</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-for="ev in events" :key="ev.id">
                          <td>{{ new Date(ev.receivedAt).toLocaleString() }}</td>
                          <td>{{ ev.source }}</td>
                          <td>
                            <VIcon :icon="ev.isProcessed ? 'tabler-check' : 'tabler-clock'" :color="ev.isProcessed ? 'success' : 'warning'" size="16" />
                          </td>
                          <td class="text-error text-caption">{{ ev.error ?? '' }}</td>
                        </tr>
                        <tr v-if="!events.length">
                          <td colspan="4" class="text-center text-medium-emphasis">{{ $gettext('No events yet') }}</td>
                        </tr>
                      </tbody>
                    </VTable>
                  </VCardText>
                </VCard>
              </td>
            </tr>
          </template>
          <tr v-if="!requests.length">
            <td :colspan="7" class="text-center text-medium-emphasis py-4">{{ $gettext('No tracking subscriptions') }}</td>
          </tr>
        </tbody>
      </VTable>
    </VCardText>
  </VCard>
</template>
```

- [ ] **Step 5: Modify `src/views/shipment/ShipmentTracking.vue` — add Subscriptions tab**

Add the import and new tab entry. Final file:

```vue
<script setup>
import ShipmentActivities from './ShipmentActivities.vue';
import ShipmentMilestones from './ShipmentMilestones.vue';
import ShipmentNotes from './ShipmentNotes.vue';
import ShipmentTasks from './ShipmentTasks.vue';
import ShipmentTrackingSubscriptions from './ShipmentTrackingSubscriptions.vue';

const props = defineProps({
  shipment: { type: Object, default: () => ({}) },
  currentTab2: { type: String, default: 'milestones' },
})
const emit = defineEmits(['tab2Changed'])
const currentTab = ref(props.currentTab2)
const tabs = [
  { icon: 'tabler-flag-check',          title: $gettext('Milestones'),     component: ShipmentMilestones,            value: 'milestones' },
  { icon: 'tabler-checklist',           title: $gettext('Tasks'),          component: ShipmentTasks,                 value: 'tasks' },
  { icon: 'tabler-notes',               title: $gettext('Notes'),          component: ShipmentNotes,                 value: 'notes' },
  { icon: 'tabler-activity-heartbeat',  title: $gettext('Activities'),     component: ShipmentActivities,            value: 'activities' },
  { icon: 'tabler-antenna',             title: $gettext('Subscriptions'),  component: ShipmentTrackingSubscriptions, value: 'subscriptions' },
]
function onTabChange(tab2) {
  emit('tab2Changed', ['tracking', tab2])
}
onMounted(() => {
  currentTab.value = props.currentTab2
})
</script>
<template>
  <div>
    <VTabs
      v-model="currentTab"
      density="compact"
      @update:modelValue="onTabChange"
    >
      <VTab
        v-for="tab in tabs"
        class="px-0 me-6 pb-2"
        style="min-inline-size: unset;"
        :value="tab.value"
      >
        <VIcon :icon="tab.icon" class="me-2" size="21" />{{ tab.title }}
      </VTab>
    </VTabs>
    <VWindow v-model="currentTab">
      <VWindowItem
        v-for="tab in tabs"
        :key="tab.value"
        :value="tab.value"
        transition="fade-transition"
        reverse-transition="fade-transition"
      >
        <component
          :is="tab.component"
          :shipment="props.shipment"
          @shipmentChanged="$emit('shipmentChanged')"
        />
      </VWindowItem>
    </VWindow>
  </div>
</template>
```

- [ ] **Step 6: Commit**

```
git add src/services/TrackingRequestService.js src/config/tables/TrackingRequest.js src/config/tables/TrackingEventRaw.js src/views/shipment/ShipmentTrackingSubscriptions.vue src/views/shipment/ShipmentTracking.vue
git commit -m "feat: add shipment tracking subscriptions panel and Subscriptions tab"
```
_(Run in `d:\Projects\make-cargo-client-bo`)_

---

## Task 10: Documentation Guide

**File:**
- Create: `d:\Projects\make-cargo-client\docs\guides\container-tracking.md`

- [ ] **Step 1: Write guide**

Content to include:
- Architecture diagram (text-based) showing the three-repo flow
- Master API: TrackingJob entity fields, endpoints (`POST/PATCH/DELETE /api/public/tracking-job`), scheduler command
- Client API: TrackingRequest entity, webhook endpoint, CarrierEventMapping entity, idempotency rule
- Client BO: where to find Subscriptions tab (ShipmentDetail → Tracking → Subscriptions), where to manage mappings (Library → Carrier Event Mappings)
- env vars required: `APP_BASE_URL` (client API), `MESSENGER_TRANSPORT_DSN` (master API)
- How to add a new carrier connector (implement `CarrierConnectorInterface`, tag with `app.tracking.connector`)

- [ ] **Step 2: Commit**

```
git add docs/guides/container-tracking.md
git commit -m "docs: add container tracking guide"
```
_(Run in `d:\Projects\make-cargo-client`)_
