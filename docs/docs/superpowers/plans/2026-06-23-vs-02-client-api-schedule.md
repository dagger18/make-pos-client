# VS-02: Client API — Schedule Integration & Vessel Roll

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Extend the client API (`d:\Projects\make-cargo-client`) to proxy vessel sailing and flight schedule searches from the master API, add `sailingRef`/`flightRef` links to the `Booking` entity, add `VesselRoll` entity for roll tracking, and expose the required endpoints to the BO.

**Architecture:** `MasterSyncService` gets two new methods (`searchVesselSailings`, `searchFlightSchedules`) that call the master API's new public endpoints. New controllers proxy these search results to the BO. The `Booking` entity stores a nullable `sailingRef` (string ID of the master sailing) and `flightRef` for traceability. `VesselRoll` is a local entity recording when cargo is rolled. No local caching of sailings — master API owns that data.

**Tech Stack:** PHP 8.2, Symfony 6, Doctrine ORM, MySQL + SQLite dual migrations.

**Target repo:** `d:\Projects\make-cargo-client`

**Context (existing patterns):**
- `MasterSyncService` uses `X-Service-Token` header via `InterServiceTokenService::generate()`. See `src/Service/MasterSyncService.php`.
- `PortController` is the canonical example of the master-search proxy pattern. See `src/Controller/Api/PortController.php`.
- MySQL migrations: `namespace DoctrineMigrations;`, folder `migrations/mysql/`, `ALTER TABLE x ADD column`.
- SQLite migrations: `namespace SqlEngineMigrations;`, folder `migrations/sqlite/`, `ALTER TABLE x ADD COLUMN column`.
- New controllers are auto-discovered (no manual registration needed).
- New service classes must be added to `app.auto_service_locator` in `config/services.yaml`. Check the existing list around line 100.

---

## File Structure

- Modify: `src/Service/MasterSyncService.php` — add `searchVesselSailings()` and `searchFlightSchedules()`
- Create: `src/Controller/Api/VesselSailingController.php`
- Create: `src/Controller/Api/FlightScheduleController.php`
- Modify: `src/Entity/Booking.php` — add `sailingRef`, `flightRef` fields
- Create: `migrations/mysql/Version20260624010000.php`
- Create: `migrations/sqlite/Version20260624010000.php`
- Create: `src/Entity/VesselRoll.php`
- Create: `src/Repository/VesselRollRepository.php`
- Create: `src/Controller/Api/VesselRollController.php`
- Create: `migrations/mysql/Version20260624020000.php`
- Create: `migrations/sqlite/Version20260624020000.php`
- Modify: `config/serializer_groups/VesselRoll.yaml` — serializer group
- Modify: `config/services.yaml` — register VesselRollRepository if needed

---

### Task 1: Extend MasterSyncService + add proxy controllers

**Files:**
- Modify: `src/Service/MasterSyncService.php`
- Create: `src/Controller/Api/VesselSailingController.php`
- Create: `src/Controller/Api/FlightScheduleController.php`

- [ ] **Step 1: Add two new methods to `src/Service/MasterSyncService.php`**

Open `src/Service/MasterSyncService.php`. Add these two methods at the end of the class (before the closing brace). Do not change any existing methods.

```php
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
```

- [ ] **Step 2: Create `src/Controller/Api/VesselSailingController.php`**

```php
<?php
declare(strict_types=1);
namespace App\Controller\Api;

use App\Service\MasterSyncService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/vessel-sailing')]
class VesselSailingController extends AbstractController
{
    public function __construct(private readonly MasterSyncService $masterSyncService) {}

    #[Route('/search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $pol = trim($request->query->getString('pol', ''));
        $pod = trim($request->query->getString('pod', ''));
        $etdFrom = $request->query->getString('etd_from', date('Y-m-d'));
        $etdTo = $request->query->getString('etd_to', date('Y-m-d', strtotime('+60 days')));

        if (!$pol || !$pod) {
            return $this->json([]);
        }

        return $this->json($this->masterSyncService->searchVesselSailings($pol, $pod, $etdFrom, $etdTo));
    }
}
```

- [ ] **Step 3: Create `src/Controller/Api/FlightScheduleController.php`**

```php
<?php
declare(strict_types=1);
namespace App\Controller\Api;

use App\Service\MasterSyncService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/flight-schedule')]
class FlightScheduleController extends AbstractController
{
    public function __construct(private readonly MasterSyncService $masterSyncService) {}

    #[Route('/search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $origin = trim(strtoupper($request->query->getString('origin', '')));
        $destination = trim(strtoupper($request->query->getString('destination', '')));
        $date = $request->query->getString('date', date('Y-m-d'));

        if (!$origin || !$destination) {
            return $this->json([]);
        }

        return $this->json($this->masterSyncService->searchFlightSchedules($origin, $destination, $date));
    }
}
```

- [ ] **Step 4: Commit**

```bash
git add src/Service/MasterSyncService.php src/Controller/Api/VesselSailingController.php src/Controller/Api/FlightScheduleController.php
git commit -m "feat(vs-02): add vessel-sailing and flight-schedule search proxy to MasterSyncService and API controllers"
```

---

### Task 2: Add sailingRef + flightRef to Booking entity + migrations

**Files:**
- Modify: `src/Entity/Booking.php`
- Create: `migrations/mysql/Version20260624010000.php`
- Create: `migrations/sqlite/Version20260624010000.php`

- [ ] **Step 1: Read `src/Entity/Booking.php` to find the insertion point**

Find the block of field declarations. The last few fields near the vessel/cutoff section should be `etd`, `eta`, `siCutOff`, `vgmCutOff`, `cyCutOff`, `gateIn`. Insert `sailingRef` and `flightRef` after `gateIn` (or at the end of the field declarations block, before the first getter).

- [ ] **Step 2: Add `$sailingRef` and `$flightRef` to `src/Entity/Booking.php`**

In the entity class, after the last `#[ORM\Column]` field declaration and before the first getter method, add:

```php
    #[ORM\Column(length: 64, nullable: true)]
    private ?string $sailingRef = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $flightRef = null;
```

Then add getters/setters. Find where the existing vessel-related getters are (e.g., `getVesselNo()`) and add after them:

```php
    public function getSailingRef(): ?string
    {
        return $this->sailingRef;
    }

    public function setSailingRef(?string $sailingRef): static
    {
        $this->sailingRef = $sailingRef;
        return $this;
    }

    public function getFlightRef(): ?string
    {
        return $this->flightRef;
    }

    public function setFlightRef(?string $flightRef): static
    {
        $this->flightRef = $flightRef;
        return $this;
    }
```

- [ ] **Step 3: Create `migrations/mysql/Version20260624010000.php`**

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
        return 'Add sailingRef and flightRef to booking';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE booking ADD sailing_ref VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE booking ADD flight_ref VARCHAR(64) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE booking DROP sailing_ref, DROP flight_ref');
    }
}
```

- [ ] **Step 4: Create `migrations/sqlite/Version20260624010000.php`**

```php
<?php
declare(strict_types=1);
namespace SqlEngineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260624010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add sailingRef and flightRef to booking';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE booking ADD COLUMN sailing_ref VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE booking ADD COLUMN flight_ref VARCHAR(64) DEFAULT NULL');
    }

    public function down(Schema $schema): void {}
}
```

- [ ] **Step 5: Commit**

```bash
git add src/Entity/Booking.php migrations/mysql/Version20260624010000.php migrations/sqlite/Version20260624010000.php
git commit -m "feat(vs-02): add sailingRef and flightRef to Booking entity with migrations"
```

---

### Task 3: VesselRoll entity + migrations + controller + serializer

**Files:**
- Create: `src/Entity/VesselRoll.php`
- Create: `src/Repository/VesselRollRepository.php`
- Create: `migrations/mysql/Version20260624020000.php`
- Create: `migrations/sqlite/Version20260624020000.php`
- Create: `src/Controller/Api/VesselRollController.php`
- Create: `config/serializer_groups/VesselRoll.yaml`

- [ ] **Step 1: Create `src/Entity/VesselRoll.php`**

Look at `src/Entity/Shipment.php` to confirm the exact class name and namespace, then write:

```php
<?php
declare(strict_types=1);
namespace App\Entity;

use App\Repository\VesselRollRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VesselRollRepository::class)]
class VesselRoll
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Shipment $shipment;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $originalSailingRef = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $originalEtd = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $newSailingRef = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $newEtd = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $reason = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $notifiedAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $rolledBy = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $rolledAt;

    public function getId(): ?int { return $this->id; }

    public function getShipment(): Shipment { return $this->shipment; }
    public function setShipment(Shipment $shipment): static { $this->shipment = $shipment; return $this; }

    public function getOriginalSailingRef(): ?string { return $this->originalSailingRef; }
    public function setOriginalSailingRef(?string $ref): static { $this->originalSailingRef = $ref; return $this; }

    public function getOriginalEtd(): ?\DateTimeImmutable { return $this->originalEtd; }
    public function setOriginalEtd(?\DateTimeImmutable $dt): static { $this->originalEtd = $dt; return $this; }

    public function getNewSailingRef(): ?string { return $this->newSailingRef; }
    public function setNewSailingRef(?string $ref): static { $this->newSailingRef = $ref; return $this; }

    public function getNewEtd(): ?\DateTimeImmutable { return $this->newEtd; }
    public function setNewEtd(?\DateTimeImmutable $dt): static { $this->newEtd = $dt; return $this; }

    public function getReason(): ?string { return $this->reason; }
    public function setReason(?string $reason): static { $this->reason = $reason; return $this; }

    public function getNotifiedAt(): ?\DateTimeImmutable { return $this->notifiedAt; }
    public function setNotifiedAt(?\DateTimeImmutable $dt): static { $this->notifiedAt = $dt; return $this; }

    public function getRolledBy(): ?User { return $this->rolledBy; }
    public function setRolledBy(?User $user): static { $this->rolledBy = $user; return $this; }

    public function getRolledAt(): \DateTimeImmutable { return $this->rolledAt; }
    public function setRolledAt(\DateTimeImmutable $dt): static { $this->rolledAt = $dt; return $this; }
}
```

- [ ] **Step 2: Create `src/Repository/VesselRollRepository.php`**

```php
<?php
declare(strict_types=1);
namespace App\Repository;

use App\Entity\VesselRoll;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class VesselRollRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VesselRoll::class);
    }

    public function findByShipment(int $shipmentId): array
    {
        return $this->createQueryBuilder('vr')
            ->where('vr.shipment = :shipmentId')
            ->setParameter('shipmentId', $shipmentId)
            ->orderBy('vr.rolledAt', 'DESC')
            ->getQuery()
            ->getArrayResult();
    }
}
```

- [ ] **Step 3: Create MySQL migration `migrations/mysql/Version20260624020000.php`**

```php
<?php
declare(strict_types=1);
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260624020000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add vessel_roll table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE vessel_roll (
            id INT NOT NULL AUTO_INCREMENT,
            shipment_id INT NOT NULL,
            rolled_by_id INT DEFAULT NULL,
            original_sailing_ref VARCHAR(64) DEFAULT NULL,
            original_etd DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
            new_sailing_ref VARCHAR(64) DEFAULT NULL,
            new_etd DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
            reason LONGTEXT DEFAULT NULL,
            notified_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
            rolled_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
            PRIMARY KEY (id),
            INDEX IDX_vessel_roll_shipment (shipment_id),
            CONSTRAINT FK_vessel_roll_shipment FOREIGN KEY (shipment_id) REFERENCES shipment (id) ON DELETE CASCADE,
            CONSTRAINT FK_vessel_roll_rolled_by FOREIGN KEY (rolled_by_id) REFERENCES `user` (id) ON DELETE SET NULL
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE vessel_roll');
    }
}
```

- [ ] **Step 4: Create SQLite migration `migrations/sqlite/Version20260624020000.php`**

```php
<?php
declare(strict_types=1);
namespace SqlEngineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260624020000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add vessel_roll table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE vessel_roll (
            id INTEGER NOT NULL,
            shipment_id INTEGER NOT NULL,
            rolled_by_id INTEGER DEFAULT NULL,
            original_sailing_ref VARCHAR(64) DEFAULT NULL,
            original_etd DATETIME DEFAULT NULL,
            new_sailing_ref VARCHAR(64) DEFAULT NULL,
            new_etd DATETIME DEFAULT NULL,
            reason CLOB DEFAULT NULL,
            notified_at DATETIME DEFAULT NULL,
            rolled_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            CONSTRAINT FK_vessel_roll_shipment FOREIGN KEY (shipment_id) REFERENCES shipment (id) ON DELETE CASCADE
        )');
        $this->addSql('CREATE INDEX IDX_vessel_roll_shipment ON vessel_roll (shipment_id)');
    }

    public function down(Schema $schema): void {}
}
```

- [ ] **Step 5: Create `config/serializer_groups/VesselRoll.yaml`**

```yaml
App\Entity\VesselRoll:
    list:
        - id
        - originalSailingRef
        - originalEtd
        - newSailingRef
        - newEtd
        - reason
        - notifiedAt
        - rolledAt
        - rolledBy
```

- [ ] **Step 6: Create `src/Controller/Api/VesselRollController.php`**

Look at `src/Controller/Api/ShipmentMilestoneController.php` (if it exists) or `src/Controller/Api/ShipmentController.php` for the pattern of sub-resource controllers (controllers that filter by shipmentId). Then write:

```php
<?php
declare(strict_types=1);
namespace App\Controller\Api;

use App\Entity\Shipment;
use App\Entity\VesselRoll;
use App\Repository\VesselRollRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/vessel-roll')]
class VesselRollController extends AbstractController
{
    public function __construct(
        private readonly VesselRollRepository $repository,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $shipmentId = $request->query->getInt('shipmentId');
        if (!$shipmentId) {
            return $this->json([]);
        }
        return $this->json(
            $this->repository->findByShipment($shipmentId),
            200,
            [],
            ['groups' => ['list']]
        );
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $shipment = $this->em->find(Shipment::class, $data['shipmentId'] ?? 0);
        if (!$shipment) {
            return $this->json(['error' => 'Shipment not found'], 404);
        }

        $roll = new VesselRoll();
        $roll->setShipment($shipment);
        $roll->setOriginalSailingRef($data['originalSailingRef'] ?? null);
        $roll->setOriginalEtd(isset($data['originalEtd']) ? new \DateTimeImmutable($data['originalEtd']) : null);
        $roll->setNewSailingRef($data['newSailingRef'] ?? null);
        $roll->setNewEtd(isset($data['newEtd']) ? new \DateTimeImmutable($data['newEtd']) : null);
        $roll->setReason($data['reason'] ?? null);
        $roll->setRolledAt(new \DateTimeImmutable());

        $this->em->persist($roll);
        $this->em->flush();

        return $this->json($roll, 201, [], ['groups' => ['list']]);
    }

    #[Route('/{id}/notify', methods: ['PUT'])]
    public function markNotified(int $id): JsonResponse
    {
        $roll = $this->repository->find($id);
        if (!$roll) {
            return $this->json(['error' => 'Not found'], 404);
        }
        $roll->setNotifiedAt(new \DateTimeImmutable());
        $this->em->flush();
        return $this->json($roll, 200, [], ['groups' => ['list']]);
    }
}
```

- [ ] **Step 7: Commit**

```bash
git add src/Entity/VesselRoll.php src/Repository/VesselRollRepository.php migrations/mysql/Version20260624020000.php migrations/sqlite/Version20260624020000.php src/Controller/Api/VesselRollController.php config/serializer_groups/VesselRoll.yaml
git commit -m "feat(vs-02): add VesselRoll entity, repository, migrations, and controller"
```

---

### Task 4: Add sailingRef and flightRef to Booking serializer

**Files:**
- Modify: `config/serializer_groups/Booking.yaml` (check if this exists; if not, find where Booking fields are serialized)

- [ ] **Step 1: Find where Booking fields are serialized**

Check `config/serializer_groups/` for a `Booking.yaml` file. If it exists, open it and add `sailingRef` and `flightRef` to the appropriate group(s). If Booking fields are serialized through `Shipment.yaml`, find the reference there.

- [ ] **Step 2: Add the new fields**

In whichever YAML file controls Booking field serialization, add `sailingRef` and `flightRef` to the `list` group alongside the existing vessel fields (`vesselNo`, `etd`, `eta`, etc.).

Example addition to the booking list group:
```yaml
        - vesselNo
        - sailingRef
        - flightRef
        - etd
        - eta
```

- [ ] **Step 3: Commit**

```bash
git add config/serializer_groups/
git commit -m "feat(vs-02): expose sailingRef and flightRef in Booking serializer"
```
