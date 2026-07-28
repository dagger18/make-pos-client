# Transport Modes Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement four missing transport mode sub-resources — Road Freight (Truck), Rail Freight (RailBooking), Courier/Express (Parcel), and Multimodal sub-leg management — as per the spec at `docs/saas/freight-forwarder-saas-modes-and-service-types.md`.

**Architecture:** Each new mode adds one Doctrine entity with a ManyToOne FK to Shipment, one repository, dual migrations (MySQL + SQLite), and one controller following the `DangerousGoodsController` pattern (sub-resource of `/shipment/{shipmentId}/...`). Multimodal re-uses the existing `Shipment.parentJobId` column with a thin controller; no new entity needed.

**Tech Stack:** Symfony 7, Doctrine ORM, PHP 8.2, PHPUnit (via `php bin/phpunit`), MySQL + SQLite dual migrations, namespace `DoctrineMigrations` (MySQL) / `SqlEngineMigrations` (SQLite).

---

## File Structure

### New files to create

| File | Purpose |
|---|---|
| `src/Module/Operations/Entity/Truck.php` | ORM entity for FTL truck job details |
| `src/Module/Operations/Repository/TruckRepository.php` | Extends BaseRepository, adds `findByShipment()` |
| `src/Module/Operations/Controller/TruckController.php` | CRUD sub-resource of `/shipment/{shipmentId}/truck` |
| `src/Module/Operations/Entity/RailBooking.php` | ORM entity for RAL rail booking details |
| `src/Module/Operations/Repository/RailBookingRepository.php` | Extends BaseRepository, adds `findByShipment()` |
| `src/Module/Operations/Controller/RailBookingController.php` | CRUD sub-resource of `/shipment/{shipmentId}/rail-booking` |
| `src/Module/Operations/Entity/Parcel.php` | ORM entity for COU parcel/courier details |
| `src/Module/Operations/Repository/ParcelRepository.php` | Extends BaseRepository, adds `findByShipment()` |
| `src/Module/Operations/Controller/ParcelController.php` | CRUD sub-resource of `/shipment/{shipmentId}/parcel` |
| `src/Module/Operations/Controller/ShipmentLegController.php` | List/add MMD sub-legs using `Shipment.parentJobId` |
| `migrations/mysql/Version20260625170000.php` | Create `truck` table |
| `migrations/sqlite/Version20260625170000.php` | Create `truck` table (SQLite) |
| `migrations/mysql/Version20260625180000.php` | Create `rail_booking` table |
| `migrations/sqlite/Version20260625180000.php` | Create `rail_booking` table (SQLite) |
| `migrations/mysql/Version20260625190000.php` | Create `parcel` table |
| `migrations/sqlite/Version20260625190000.php` | Create `parcel` table (SQLite) |
| `docs/guides/transport-modes.md` | Operator guide for all 4 new modes |

### Files to read (no changes)
- `src/Module/Operations/Entity/DangerousGoods.php` — entity pattern to follow
- `src/Module/Operations/Controller/DangerousGoodsController.php` — controller pattern to follow
- `src/Module/Operations/Repository/DangerousGoodsRepository.php` — repository pattern to follow
- `src/Module/Operations/Entity/Shipment.php` — confirms `parentJobId: ?int` exists; need `getId()`
- `migrations/mysql/Version20260625010000.php` — migration format

---

## Task 1: Truck Entity + Repository

**Files:**
- Create: `src/Module/Operations/Entity/Truck.php`
- Create: `src/Module/Operations/Repository/TruckRepository.php`

- [ ] **Step 1: Create the Truck entity**

```php
<?php
namespace App\Module\Operations\Entity;

use App\Module\Carrier\Entity\Provider;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Module\Operations\Repository\TruckRepository;

#[ORM\Entity(repositoryClass: TruckRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Truck
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Shipment $shipment = null;

    #[ORM\Column(length: 32)]
    private string $truckType = '';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $payloadKg = null;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $truckPlate = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $driverName = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Provider $haulier = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $pickupAddress = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $deliveryAddress = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $scheduledPickup = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $scheduledDelivery = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $actualPickup = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $actualDelivery = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $podSignedBy = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $podImageUrl = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\PrePersist]
    public function prePersist(): void { $this->createdAt = new \DateTime(); }

    #[ORM\PreUpdate]
    public function preUpdate(): void { $this->updatedAt = new \DateTime(); }

    public function getId(): ?int { return $this->id; }

    public function getShipment(): ?Shipment { return $this->shipment; }
    public function setShipment(?Shipment $s): static { $this->shipment = $s; return $this; }

    public function getTruckType(): string { return $this->truckType; }
    public function setTruckType(string $v): static { $this->truckType = $v; return $this; }

    public function getPayloadKg(): ?string { return $this->payloadKg; }
    public function setPayloadKg(?string $v): static { $this->payloadKg = $v; return $this; }

    public function getTruckPlate(): ?string { return $this->truckPlate; }
    public function setTruckPlate(?string $v): static { $this->truckPlate = $v; return $this; }

    public function getDriverName(): ?string { return $this->driverName; }
    public function setDriverName(?string $v): static { $this->driverName = $v; return $this; }

    public function getHaulier(): ?Provider { return $this->haulier; }
    public function setHaulier(?Provider $v): static { $this->haulier = $v; return $this; }

    public function getPickupAddress(): ?string { return $this->pickupAddress; }
    public function setPickupAddress(?string $v): static { $this->pickupAddress = $v; return $this; }

    public function getDeliveryAddress(): ?string { return $this->deliveryAddress; }
    public function setDeliveryAddress(?string $v): static { $this->deliveryAddress = $v; return $this; }

    public function getScheduledPickup(): ?\DateTimeInterface { return $this->scheduledPickup; }
    public function setScheduledPickup(?\DateTimeInterface $v): static { $this->scheduledPickup = $v; return $this; }

    public function getScheduledDelivery(): ?\DateTimeInterface { return $this->scheduledDelivery; }
    public function setScheduledDelivery(?\DateTimeInterface $v): static { $this->scheduledDelivery = $v; return $this; }

    public function getActualPickup(): ?\DateTimeInterface { return $this->actualPickup; }
    public function setActualPickup(?\DateTimeInterface $v): static { $this->actualPickup = $v; return $this; }

    public function getActualDelivery(): ?\DateTimeInterface { return $this->actualDelivery; }
    public function setActualDelivery(?\DateTimeInterface $v): static { $this->actualDelivery = $v; return $this; }

    public function getPodSignedBy(): ?string { return $this->podSignedBy; }
    public function setPodSignedBy(?string $v): static { $this->podSignedBy = $v; return $this; }

    public function getPodImageUrl(): ?string { return $this->podImageUrl; }
    public function setPodImageUrl(?string $v): static { $this->podImageUrl = $v; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeInterface { return $this->updatedAt; }
}
```

- [ ] **Step 2: Create the TruckRepository**

```php
<?php
namespace App\Module\Operations\Repository;

use App\Module\Core\Repository\BaseRepository;

class TruckRepository extends BaseRepository
{
    public function findByShipment(int $shipmentId): array
    {
        return $this->findBy(['shipment' => $shipmentId], ['id' => 'ASC']);
    }
}
```

- [ ] **Step 3: Verify PHP syntax**

```
php -l src/Module/Operations/Entity/Truck.php
php -l src/Module/Operations/Repository/TruckRepository.php
```

Expected: `No syntax errors detected`

- [ ] **Step 4: Commit**

```bash
git add src/Module/Operations/Entity/Truck.php src/Module/Operations/Repository/TruckRepository.php
git commit -m "feat: add Truck entity and repository for Road Freight (FTL)"
```

---

## Task 2: Truck Migrations (MySQL + SQLite)

**Files:**
- Create: `migrations/mysql/Version20260625170000.php`
- Create: `migrations/sqlite/Version20260625170000.php`

- [ ] **Step 1: Create MySQL migration**

```php
<?php
declare(strict_types=1);
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260625170000 extends AbstractMigration
{
    public function getDescription(): string { return 'Create truck table for Road Freight (FTL)'; }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE truck (
            id INT AUTO_INCREMENT NOT NULL,
            shipment_id INT NOT NULL,
            haulier_id INT DEFAULT NULL,
            truck_type VARCHAR(32) NOT NULL,
            payload_kg NUMERIC(10,2) DEFAULT NULL,
            truck_plate VARCHAR(16) DEFAULT NULL,
            driver_name VARCHAR(64) DEFAULT NULL,
            pickup_address LONGTEXT DEFAULT NULL,
            delivery_address LONGTEXT DEFAULT NULL,
            scheduled_pickup DATETIME DEFAULT NULL,
            scheduled_delivery DATETIME DEFAULT NULL,
            actual_pickup DATETIME DEFAULT NULL,
            actual_delivery DATETIME DEFAULT NULL,
            pod_signed_by VARCHAR(64) DEFAULT NULL,
            pod_image_url LONGTEXT DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            INDEX IDX_truck_shipment (shipment_id),
            INDEX IDX_truck_haulier (haulier_id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql("ALTER TABLE truck
            ADD CONSTRAINT FK_truck_shipment FOREIGN KEY (shipment_id) REFERENCES shipment (id) ON DELETE CASCADE,
            ADD CONSTRAINT FK_truck_haulier FOREIGN KEY (haulier_id) REFERENCES provider (id) ON DELETE SET NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE truck DROP FOREIGN KEY FK_truck_shipment");
        $this->addSql("ALTER TABLE truck DROP FOREIGN KEY FK_truck_haulier");
        $this->addSql("DROP TABLE truck");
    }
}
```

- [ ] **Step 2: Create SQLite migration**

```php
<?php
declare(strict_types=1);
namespace SqlEngineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260625170000 extends AbstractMigration
{
    public function getDescription(): string { return 'Create truck table for Road Freight (FTL) (SQLite)'; }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE truck (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            shipment_id INTEGER NOT NULL,
            haulier_id INTEGER DEFAULT NULL,
            truck_type VARCHAR(32) NOT NULL,
            payload_kg NUMERIC(10,2) DEFAULT NULL,
            truck_plate VARCHAR(16) DEFAULT NULL,
            driver_name VARCHAR(64) DEFAULT NULL,
            pickup_address CLOB DEFAULT NULL,
            delivery_address CLOB DEFAULT NULL,
            scheduled_pickup DATETIME DEFAULT NULL,
            scheduled_delivery DATETIME DEFAULT NULL,
            actual_pickup DATETIME DEFAULT NULL,
            actual_delivery DATETIME DEFAULT NULL,
            pod_signed_by VARCHAR(64) DEFAULT NULL,
            pod_image_url CLOB DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME DEFAULT NULL,
            FOREIGN KEY (shipment_id) REFERENCES shipment (id) ON DELETE CASCADE,
            FOREIGN KEY (haulier_id) REFERENCES provider (id) ON DELETE SET NULL
        )");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DROP TABLE truck");
    }
}
```

- [ ] **Step 3: Verify PHP syntax**

```
php -l migrations/mysql/Version20260625170000.php
php -l migrations/sqlite/Version20260625170000.php
```

Expected: `No syntax errors detected`

- [ ] **Step 4: Commit**

```bash
git add migrations/mysql/Version20260625170000.php migrations/sqlite/Version20260625170000.php
git commit -m "feat: add truck table migration (mysql + sqlite)"
```

---

## Task 3: TruckController

**Files:**
- Create: `src/Module/Operations/Controller/TruckController.php`

- [ ] **Step 1: Create the controller**

```php
<?php
namespace App\Module\Operations\Controller;

use App\Misc\Attribute\AppModule;
use App\Module\Carrier\Repository\ProviderRepository;
use App\Module\Operations\Entity\Truck;
use App\Module\Operations\Repository\ShipmentRepository;
use App\Module\Operations\Repository\TruckRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/shipment/{shipmentId}/truck')]
#[IsGranted('ROLE_USER')]
#[AppModule('operations')]
class TruckController extends AbstractController
{
    public function __construct(
        private readonly TruckRepository $truckRepository,
        private readonly ShipmentRepository $shipmentRepository,
        private readonly ProviderRepository $providerRepository,
    ) {}

    #[Route('', methods: ['GET'])]
    public function list(int $shipmentId): JsonResponse
    {
        return $this->json(array_map(
            fn($t) => $this->serialize($t),
            $this->truckRepository->findByShipment($shipmentId)
        ));
    }

    #[Route('', methods: ['POST'])]
    public function create(int $shipmentId, Request $request): JsonResponse
    {
        $shipment = $this->shipmentRepository->find($shipmentId);
        if (!$shipment) throw $this->createNotFoundException();

        $body = json_decode($request->getContent(), true) ?? [];
        $truck = $this->hydrate(new Truck(), $body);
        $truck->setShipment($shipment);

        $errors = $this->validate($truck, $body);
        if ($errors) return $this->json(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->truckRepository->save($truck);
        return $this->json($this->serialize($truck), Response::HTTP_CREATED);
    }

    #[Route('/{truckId}', methods: ['PUT'])]
    public function update(int $shipmentId, int $truckId, Request $request): JsonResponse
    {
        $truck = $this->truckRepository->find($truckId);
        if (!$truck || $truck->getShipment()->getId() !== $shipmentId) throw $this->createNotFoundException();

        $body = json_decode($request->getContent(), true) ?? [];
        $this->hydrate($truck, $body);

        $errors = $this->validate($truck, $body);
        if ($errors) return $this->json(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->truckRepository->save($truck);
        return $this->json($this->serialize($truck));
    }

    #[Route('/{truckId}', methods: ['DELETE'])]
    public function delete(int $shipmentId, int $truckId): JsonResponse
    {
        $truck = $this->truckRepository->find($truckId);
        if (!$truck || $truck->getShipment()->getId() !== $shipmentId) throw $this->createNotFoundException();

        $this->truckRepository->delete($truck);
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    private function hydrate(Truck $truck, array $body): Truck
    {
        if (isset($body['truckType']))         $truck->setTruckType($body['truckType']);
        if (array_key_exists('payloadKg', $body))       $truck->setPayloadKg($body['payloadKg'] !== null ? (string) $body['payloadKg'] : null);
        if (array_key_exists('truckPlate', $body))      $truck->setTruckPlate($body['truckPlate'] ?: null);
        if (array_key_exists('driverName', $body))      $truck->setDriverName($body['driverName'] ?: null);
        if (array_key_exists('pickupAddress', $body))   $truck->setPickupAddress($body['pickupAddress'] ?: null);
        if (array_key_exists('deliveryAddress', $body)) $truck->setDeliveryAddress($body['deliveryAddress'] ?: null);
        if (array_key_exists('podSignedBy', $body))     $truck->setPodSignedBy($body['podSignedBy'] ?: null);
        if (array_key_exists('podImageUrl', $body))     $truck->setPodImageUrl($body['podImageUrl'] ?: null);

        foreach (['scheduledPickup', 'scheduledDelivery', 'actualPickup', 'actualDelivery'] as $field) {
            if (array_key_exists($field, $body)) {
                $setter = 'set' . ucfirst($field);
                $truck->$setter($body[$field] ? new \DateTime($body[$field]) : null);
            }
        }

        if (array_key_exists('haulierId', $body)) {
            $truck->setHaulier($body['haulierId'] ? $this->providerRepository->find((int) $body['haulierId']) : null);
        }

        return $truck;
    }

    private function validate(Truck $truck, array $body): array
    {
        $errors = [];
        if ($truck->getTruckType() === '') $errors[] = 'truckType is required.';
        $allowed = ['BOX', 'CURTAINSIDER', 'FLATBED', 'REEFER', 'TANKER'];
        if ($truck->getTruckType() !== '' && !in_array($truck->getTruckType(), $allowed, true)) {
            $errors[] = 'truckType must be one of: ' . implode(', ', $allowed);
        }
        return $errors;
    }

    private function serialize(Truck $truck): array
    {
        return [
            'id'               => $truck->getId(),
            'truckType'        => $truck->getTruckType(),
            'payloadKg'        => $truck->getPayloadKg(),
            'truckPlate'       => $truck->getTruckPlate(),
            'driverName'       => $truck->getDriverName(),
            'haulier'          => $truck->getHaulier() ? ['id' => $truck->getHaulier()->getId(), 'name' => $truck->getHaulier()->getName()] : null,
            'pickupAddress'    => $truck->getPickupAddress(),
            'deliveryAddress'  => $truck->getDeliveryAddress(),
            'scheduledPickup'  => $truck->getScheduledPickup()?->format(\DateTimeInterface::ATOM),
            'scheduledDelivery'=> $truck->getScheduledDelivery()?->format(\DateTimeInterface::ATOM),
            'actualPickup'     => $truck->getActualPickup()?->format(\DateTimeInterface::ATOM),
            'actualDelivery'   => $truck->getActualDelivery()?->format(\DateTimeInterface::ATOM),
            'podSignedBy'      => $truck->getPodSignedBy(),
            'podImageUrl'      => $truck->getPodImageUrl(),
            'createdAt'        => $truck->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updatedAt'        => $truck->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }
}
```

- [ ] **Step 2: Verify PHP syntax**

```
php -l src/Module/Operations/Controller/TruckController.php
```

Expected: `No syntax errors detected`

- [ ] **Step 3: Check that `Provider` entity has a `getName()` method**

```
grep -n "function getName" src/Module/Carrier/Entity/Provider.php
```

Expected: line showing `public function getName()`. If the method is named differently (e.g., `getCompanyName()`), update the serialize method accordingly.

- [ ] **Step 4: Commit**

```bash
git add src/Module/Operations/Controller/TruckController.php
git commit -m "feat: add TruckController for Road Freight (FTL) sub-resource"
```

---

## Task 4: RailBooking Entity + Repository

**Files:**
- Create: `src/Module/Operations/Entity/RailBooking.php`
- Create: `src/Module/Operations/Repository/RailBookingRepository.php`

- [ ] **Step 1: Create the RailBooking entity**

```php
<?php
namespace App\Module\Operations\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Module\Operations\Repository\RailBookingRepository;

#[ORM\Entity(repositoryClass: RailBookingRepository::class)]
#[ORM\HasLifecycleCallbacks]
class RailBooking
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Shipment $shipment = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $trainService = null;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $departureIcd = null;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $arrivalIcd = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $operator = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $cimWaybillNumber = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $cimWaybillDate = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $departureDate = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $arrivalDate = null;

    #[ORM\Column(nullable: true)]
    private ?int $containerCount = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $note = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\PrePersist]
    public function prePersist(): void { $this->createdAt = new \DateTime(); }

    #[ORM\PreUpdate]
    public function preUpdate(): void { $this->updatedAt = new \DateTime(); }

    public function getId(): ?int { return $this->id; }

    public function getShipment(): ?Shipment { return $this->shipment; }
    public function setShipment(?Shipment $s): static { $this->shipment = $s; return $this; }

    public function getTrainService(): ?string { return $this->trainService; }
    public function setTrainService(?string $v): static { $this->trainService = $v; return $this; }

    public function getDepartureIcd(): ?string { return $this->departureIcd; }
    public function setDepartureIcd(?string $v): static { $this->departureIcd = $v; return $this; }

    public function getArrivalIcd(): ?string { return $this->arrivalIcd; }
    public function setArrivalIcd(?string $v): static { $this->arrivalIcd = $v; return $this; }

    public function getOperator(): ?string { return $this->operator; }
    public function setOperator(?string $v): static { $this->operator = $v; return $this; }

    public function getCimWaybillNumber(): ?string { return $this->cimWaybillNumber; }
    public function setCimWaybillNumber(?string $v): static { $this->cimWaybillNumber = $v; return $this; }

    public function getCimWaybillDate(): ?\DateTimeInterface { return $this->cimWaybillDate; }
    public function setCimWaybillDate(?\DateTimeInterface $v): static { $this->cimWaybillDate = $v; return $this; }

    public function getDepartureDate(): ?\DateTimeInterface { return $this->departureDate; }
    public function setDepartureDate(?\DateTimeInterface $v): static { $this->departureDate = $v; return $this; }

    public function getArrivalDate(): ?\DateTimeInterface { return $this->arrivalDate; }
    public function setArrivalDate(?\DateTimeInterface $v): static { $this->arrivalDate = $v; return $this; }

    public function getContainerCount(): ?int { return $this->containerCount; }
    public function setContainerCount(?int $v): static { $this->containerCount = $v; return $this; }

    public function getNote(): ?string { return $this->note; }
    public function setNote(?string $v): static { $this->note = $v; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeInterface { return $this->updatedAt; }
}
```

- [ ] **Step 2: Create the RailBookingRepository**

```php
<?php
namespace App\Module\Operations\Repository;

use App\Module\Core\Repository\BaseRepository;

class RailBookingRepository extends BaseRepository
{
    public function findByShipment(int $shipmentId): array
    {
        return $this->findBy(['shipment' => $shipmentId], ['id' => 'ASC']);
    }
}
```

- [ ] **Step 3: Verify PHP syntax**

```
php -l src/Module/Operations/Entity/RailBooking.php
php -l src/Module/Operations/Repository/RailBookingRepository.php
```

Expected: `No syntax errors detected`

- [ ] **Step 4: Commit**

```bash
git add src/Module/Operations/Entity/RailBooking.php src/Module/Operations/Repository/RailBookingRepository.php
git commit -m "feat: add RailBooking entity and repository for Rail Freight (RAL)"
```

---

## Task 5: RailBooking Migrations (MySQL + SQLite)

**Files:**
- Create: `migrations/mysql/Version20260625180000.php`
- Create: `migrations/sqlite/Version20260625180000.php`

- [ ] **Step 1: Create MySQL migration**

```php
<?php
declare(strict_types=1);
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260625180000 extends AbstractMigration
{
    public function getDescription(): string { return 'Create rail_booking table for Rail Freight (RAL)'; }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE rail_booking (
            id INT AUTO_INCREMENT NOT NULL,
            shipment_id INT NOT NULL,
            train_service VARCHAR(64) DEFAULT NULL,
            departure_icd VARCHAR(16) DEFAULT NULL,
            arrival_icd VARCHAR(16) DEFAULT NULL,
            operator VARCHAR(64) DEFAULT NULL,
            cim_waybill_number VARCHAR(64) DEFAULT NULL,
            cim_waybill_date DATE DEFAULT NULL,
            departure_date DATETIME DEFAULT NULL,
            arrival_date DATETIME DEFAULT NULL,
            container_count INT DEFAULT NULL,
            note LONGTEXT DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            INDEX IDX_rail_booking_shipment (shipment_id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql("ALTER TABLE rail_booking
            ADD CONSTRAINT FK_rail_booking_shipment FOREIGN KEY (shipment_id) REFERENCES shipment (id) ON DELETE CASCADE");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE rail_booking DROP FOREIGN KEY FK_rail_booking_shipment");
        $this->addSql("DROP TABLE rail_booking");
    }
}
```

- [ ] **Step 2: Create SQLite migration**

```php
<?php
declare(strict_types=1);
namespace SqlEngineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260625180000 extends AbstractMigration
{
    public function getDescription(): string { return 'Create rail_booking table for Rail Freight (RAL) (SQLite)'; }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE rail_booking (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            shipment_id INTEGER NOT NULL,
            train_service VARCHAR(64) DEFAULT NULL,
            departure_icd VARCHAR(16) DEFAULT NULL,
            arrival_icd VARCHAR(16) DEFAULT NULL,
            operator VARCHAR(64) DEFAULT NULL,
            cim_waybill_number VARCHAR(64) DEFAULT NULL,
            cim_waybill_date DATE DEFAULT NULL,
            departure_date DATETIME DEFAULT NULL,
            arrival_date DATETIME DEFAULT NULL,
            container_count INTEGER DEFAULT NULL,
            note CLOB DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME DEFAULT NULL,
            FOREIGN KEY (shipment_id) REFERENCES shipment (id) ON DELETE CASCADE
        )");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DROP TABLE rail_booking");
    }
}
```

- [ ] **Step 3: Verify PHP syntax**

```
php -l migrations/mysql/Version20260625180000.php
php -l migrations/sqlite/Version20260625180000.php
```

Expected: `No syntax errors detected`

- [ ] **Step 4: Commit**

```bash
git add migrations/mysql/Version20260625180000.php migrations/sqlite/Version20260625180000.php
git commit -m "feat: add rail_booking table migration (mysql + sqlite)"
```

---

## Task 6: RailBookingController

**Files:**
- Create: `src/Module/Operations/Controller/RailBookingController.php`

- [ ] **Step 1: Create the controller**

```php
<?php
namespace App\Module\Operations\Controller;

use App\Misc\Attribute\AppModule;
use App\Module\Operations\Entity\RailBooking;
use App\Module\Operations\Repository\RailBookingRepository;
use App\Module\Operations\Repository\ShipmentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/shipment/{shipmentId}/rail-booking')]
#[IsGranted('ROLE_USER')]
#[AppModule('operations')]
class RailBookingController extends AbstractController
{
    public function __construct(
        private readonly RailBookingRepository $railBookingRepository,
        private readonly ShipmentRepository $shipmentRepository,
    ) {}

    #[Route('', methods: ['GET'])]
    public function list(int $shipmentId): JsonResponse
    {
        return $this->json(array_map(
            fn($rb) => $this->serialize($rb),
            $this->railBookingRepository->findByShipment($shipmentId)
        ));
    }

    #[Route('', methods: ['POST'])]
    public function create(int $shipmentId, Request $request): JsonResponse
    {
        $shipment = $this->shipmentRepository->find($shipmentId);
        if (!$shipment) throw $this->createNotFoundException();

        $body = json_decode($request->getContent(), true) ?? [];
        $rb = $this->hydrate(new RailBooking(), $body);
        $rb->setShipment($shipment);

        $this->railBookingRepository->save($rb);
        return $this->json($this->serialize($rb), Response::HTTP_CREATED);
    }

    #[Route('/{rbId}', methods: ['PUT'])]
    public function update(int $shipmentId, int $rbId, Request $request): JsonResponse
    {
        $rb = $this->railBookingRepository->find($rbId);
        if (!$rb || $rb->getShipment()->getId() !== $shipmentId) throw $this->createNotFoundException();

        $body = json_decode($request->getContent(), true) ?? [];
        $this->hydrate($rb, $body);

        $this->railBookingRepository->save($rb);
        return $this->json($this->serialize($rb));
    }

    #[Route('/{rbId}', methods: ['DELETE'])]
    public function delete(int $shipmentId, int $rbId): JsonResponse
    {
        $rb = $this->railBookingRepository->find($rbId);
        if (!$rb || $rb->getShipment()->getId() !== $shipmentId) throw $this->createNotFoundException();

        $this->railBookingRepository->delete($rb);
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    private function hydrate(RailBooking $rb, array $body): RailBooking
    {
        if (array_key_exists('trainService', $body))      $rb->setTrainService($body['trainService'] ?: null);
        if (array_key_exists('departureIcd', $body))      $rb->setDepartureIcd($body['departureIcd'] ?: null);
        if (array_key_exists('arrivalIcd', $body))        $rb->setArrivalIcd($body['arrivalIcd'] ?: null);
        if (array_key_exists('operator', $body))          $rb->setOperator($body['operator'] ?: null);
        if (array_key_exists('cimWaybillNumber', $body))  $rb->setCimWaybillNumber($body['cimWaybillNumber'] ?: null);
        if (array_key_exists('cimWaybillDate', $body))    $rb->setCimWaybillDate($body['cimWaybillDate'] ? new \DateTime($body['cimWaybillDate']) : null);
        if (array_key_exists('departureDate', $body))     $rb->setDepartureDate($body['departureDate'] ? new \DateTime($body['departureDate']) : null);
        if (array_key_exists('arrivalDate', $body))       $rb->setArrivalDate($body['arrivalDate'] ? new \DateTime($body['arrivalDate']) : null);
        if (array_key_exists('containerCount', $body))    $rb->setContainerCount($body['containerCount'] !== null ? (int) $body['containerCount'] : null);
        if (array_key_exists('note', $body))              $rb->setNote($body['note'] ?: null);
        return $rb;
    }

    private function serialize(RailBooking $rb): array
    {
        return [
            'id'               => $rb->getId(),
            'trainService'     => $rb->getTrainService(),
            'departureIcd'     => $rb->getDepartureIcd(),
            'arrivalIcd'       => $rb->getArrivalIcd(),
            'operator'         => $rb->getOperator(),
            'cimWaybillNumber' => $rb->getCimWaybillNumber(),
            'cimWaybillDate'   => $rb->getCimWaybillDate()?->format('Y-m-d'),
            'departureDate'    => $rb->getDepartureDate()?->format(\DateTimeInterface::ATOM),
            'arrivalDate'      => $rb->getArrivalDate()?->format(\DateTimeInterface::ATOM),
            'containerCount'   => $rb->getContainerCount(),
            'note'             => $rb->getNote(),
            'createdAt'        => $rb->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updatedAt'        => $rb->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }
}
```

- [ ] **Step 2: Verify PHP syntax**

```
php -l src/Module/Operations/Controller/RailBookingController.php
```

Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add src/Module/Operations/Controller/RailBookingController.php
git commit -m "feat: add RailBookingController for Rail Freight (RAL) sub-resource"
```

---

## Task 7: Parcel Entity + Repository

**Files:**
- Create: `src/Module/Operations/Entity/Parcel.php`
- Create: `src/Module/Operations/Repository/ParcelRepository.php`

- [ ] **Step 1: Create the Parcel entity**

```php
<?php
namespace App\Module\Operations\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Module\Operations\Repository\ParcelRepository;

#[ORM\Entity(repositoryClass: ParcelRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Parcel
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Shipment $shipment = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $trackingNumber = null;

    #[ORM\Column(length: 16)]
    private string $serviceLevel = '';

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $integrator = null;

    #[ORM\Column]
    private int $pieces = 1;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 3)]
    private string $grossWeightKg = '0';

    #[ORM\Column(type: Types::DECIMAL, precision: 20, scale: 6, nullable: true)]
    private ?string $declaredValue = null;

    #[ORM\Column(length: 3, nullable: true)]
    private ?string $declaredCurrency = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\PrePersist]
    public function prePersist(): void { $this->createdAt = new \DateTime(); }

    #[ORM\PreUpdate]
    public function preUpdate(): void { $this->updatedAt = new \DateTime(); }

    public function getId(): ?int { return $this->id; }

    public function getShipment(): ?Shipment { return $this->shipment; }
    public function setShipment(?Shipment $s): static { $this->shipment = $s; return $this; }

    public function getTrackingNumber(): ?string { return $this->trackingNumber; }
    public function setTrackingNumber(?string $v): static { $this->trackingNumber = $v; return $this; }

    public function getServiceLevel(): string { return $this->serviceLevel; }
    public function setServiceLevel(string $v): static { $this->serviceLevel = $v; return $this; }

    public function getIntegrator(): ?string { return $this->integrator; }
    public function setIntegrator(?string $v): static { $this->integrator = $v; return $this; }

    public function getPieces(): int { return $this->pieces; }
    public function setPieces(int $v): static { $this->pieces = $v; return $this; }

    public function getGrossWeightKg(): string { return $this->grossWeightKg; }
    public function setGrossWeightKg(string $v): static { $this->grossWeightKg = $v; return $this; }

    public function getDeclaredValue(): ?string { return $this->declaredValue; }
    public function setDeclaredValue(?string $v): static { $this->declaredValue = $v; return $this; }

    public function getDeclaredCurrency(): ?string { return $this->declaredCurrency; }
    public function setDeclaredCurrency(?string $v): static { $this->declaredCurrency = $v; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeInterface { return $this->updatedAt; }
}
```

- [ ] **Step 2: Create the ParcelRepository**

```php
<?php
namespace App\Module\Operations\Repository;

use App\Module\Core\Repository\BaseRepository;

class ParcelRepository extends BaseRepository
{
    public function findByShipment(int $shipmentId): array
    {
        return $this->findBy(['shipment' => $shipmentId], ['id' => 'ASC']);
    }
}
```

- [ ] **Step 3: Verify PHP syntax**

```
php -l src/Module/Operations/Entity/Parcel.php
php -l src/Module/Operations/Repository/ParcelRepository.php
```

Expected: `No syntax errors detected`

- [ ] **Step 4: Commit**

```bash
git add src/Module/Operations/Entity/Parcel.php src/Module/Operations/Repository/ParcelRepository.php
git commit -m "feat: add Parcel entity and repository for Courier/Express (COU)"
```

---

## Task 8: Parcel Migrations (MySQL + SQLite)

**Files:**
- Create: `migrations/mysql/Version20260625190000.php`
- Create: `migrations/sqlite/Version20260625190000.php`

- [ ] **Step 1: Create MySQL migration**

```php
<?php
declare(strict_types=1);
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260625190000 extends AbstractMigration
{
    public function getDescription(): string { return 'Create parcel table for Courier/Express (COU)'; }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE parcel (
            id INT AUTO_INCREMENT NOT NULL,
            shipment_id INT NOT NULL,
            tracking_number VARCHAR(64) DEFAULT NULL,
            service_level VARCHAR(16) NOT NULL,
            integrator VARCHAR(32) DEFAULT NULL,
            pieces INT NOT NULL DEFAULT 1,
            gross_weight_kg NUMERIC(10,3) NOT NULL DEFAULT 0,
            declared_value NUMERIC(20,6) DEFAULT NULL,
            declared_currency CHAR(3) DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            INDEX IDX_parcel_shipment (shipment_id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql("ALTER TABLE parcel
            ADD CONSTRAINT FK_parcel_shipment FOREIGN KEY (shipment_id) REFERENCES shipment (id) ON DELETE CASCADE");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE parcel DROP FOREIGN KEY FK_parcel_shipment");
        $this->addSql("DROP TABLE parcel");
    }
}
```

- [ ] **Step 2: Create SQLite migration**

```php
<?php
declare(strict_types=1);
namespace SqlEngineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260625190000 extends AbstractMigration
{
    public function getDescription(): string { return 'Create parcel table for Courier/Express (COU) (SQLite)'; }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE parcel (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            shipment_id INTEGER NOT NULL,
            tracking_number VARCHAR(64) DEFAULT NULL,
            service_level VARCHAR(16) NOT NULL,
            integrator VARCHAR(32) DEFAULT NULL,
            pieces INTEGER NOT NULL DEFAULT 1,
            gross_weight_kg NUMERIC(10,3) NOT NULL DEFAULT 0,
            declared_value NUMERIC(20,6) DEFAULT NULL,
            declared_currency CHAR(3) DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME DEFAULT NULL,
            FOREIGN KEY (shipment_id) REFERENCES shipment (id) ON DELETE CASCADE
        )");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DROP TABLE parcel");
    }
}
```

- [ ] **Step 3: Verify PHP syntax**

```
php -l migrations/mysql/Version20260625190000.php
php -l migrations/sqlite/Version20260625190000.php
```

Expected: `No syntax errors detected`

- [ ] **Step 4: Commit**

```bash
git add migrations/mysql/Version20260625190000.php migrations/sqlite/Version20260625190000.php
git commit -m "feat: add parcel table migration (mysql + sqlite)"
```

---

## Task 9: ParcelController

**Files:**
- Create: `src/Module/Operations/Controller/ParcelController.php`

- [ ] **Step 1: Create the controller**

```php
<?php
namespace App\Module\Operations\Controller;

use App\Misc\Attribute\AppModule;
use App\Module\Operations\Entity\Parcel;
use App\Module\Operations\Repository\ParcelRepository;
use App\Module\Operations\Repository\ShipmentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/shipment/{shipmentId}/parcel')]
#[IsGranted('ROLE_USER')]
#[AppModule('operations')]
class ParcelController extends AbstractController
{
    public function __construct(
        private readonly ParcelRepository $parcelRepository,
        private readonly ShipmentRepository $shipmentRepository,
    ) {}

    #[Route('', methods: ['GET'])]
    public function list(int $shipmentId): JsonResponse
    {
        return $this->json(array_map(
            fn($p) => $this->serialize($p),
            $this->parcelRepository->findByShipment($shipmentId)
        ));
    }

    #[Route('', methods: ['POST'])]
    public function create(int $shipmentId, Request $request): JsonResponse
    {
        $shipment = $this->shipmentRepository->find($shipmentId);
        if (!$shipment) throw $this->createNotFoundException();

        $body = json_decode($request->getContent(), true) ?? [];
        $parcel = $this->hydrate(new Parcel(), $body);
        $parcel->setShipment($shipment);

        $errors = $this->validate($parcel, $body);
        if ($errors) return $this->json(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->parcelRepository->save($parcel);
        return $this->json($this->serialize($parcel), Response::HTTP_CREATED);
    }

    #[Route('/{parcelId}', methods: ['PUT'])]
    public function update(int $shipmentId, int $parcelId, Request $request): JsonResponse
    {
        $parcel = $this->parcelRepository->find($parcelId);
        if (!$parcel || $parcel->getShipment()->getId() !== $shipmentId) throw $this->createNotFoundException();

        $body = json_decode($request->getContent(), true) ?? [];
        $this->hydrate($parcel, $body);

        $errors = $this->validate($parcel, $body);
        if ($errors) return $this->json(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->parcelRepository->save($parcel);
        return $this->json($this->serialize($parcel));
    }

    #[Route('/{parcelId}', methods: ['DELETE'])]
    public function delete(int $shipmentId, int $parcelId): JsonResponse
    {
        $parcel = $this->parcelRepository->find($parcelId);
        if (!$parcel || $parcel->getShipment()->getId() !== $shipmentId) throw $this->createNotFoundException();

        $this->parcelRepository->delete($parcel);
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    private function hydrate(Parcel $parcel, array $body): Parcel
    {
        if (isset($body['serviceLevel']))           $parcel->setServiceLevel($body['serviceLevel']);
        if (array_key_exists('trackingNumber', $body))  $parcel->setTrackingNumber($body['trackingNumber'] ?: null);
        if (array_key_exists('integrator', $body))      $parcel->setIntegrator($body['integrator'] ?: null);
        if (isset($body['pieces']))                 $parcel->setPieces((int) $body['pieces']);
        if (isset($body['grossWeightKg']))          $parcel->setGrossWeightKg((string) $body['grossWeightKg']);
        if (array_key_exists('declaredValue', $body))   $parcel->setDeclaredValue($body['declaredValue'] !== null ? (string) $body['declaredValue'] : null);
        if (array_key_exists('declaredCurrency', $body))$parcel->setDeclaredCurrency($body['declaredCurrency'] ?: null);
        return $parcel;
    }

    private function validate(Parcel $parcel, array $body): array
    {
        $errors = [];
        if ($parcel->getServiceLevel() === '') $errors[] = 'serviceLevel is required.';
        $allowed = ['ECONOMY', 'EXPRESS', 'OVERNIGHT', 'SAME-DAY'];
        if ($parcel->getServiceLevel() !== '' && !in_array($parcel->getServiceLevel(), $allowed, true)) {
            $errors[] = 'serviceLevel must be one of: ' . implode(', ', $allowed);
        }
        if ((float) $parcel->getGrossWeightKg() <= 0) $errors[] = 'grossWeightKg must be greater than 0.';
        if ($parcel->getPieces() < 1) $errors[] = 'pieces must be at least 1.';
        return $errors;
    }

    private function serialize(Parcel $parcel): array
    {
        return [
            'id'               => $parcel->getId(),
            'trackingNumber'   => $parcel->getTrackingNumber(),
            'serviceLevel'     => $parcel->getServiceLevel(),
            'integrator'       => $parcel->getIntegrator(),
            'pieces'           => $parcel->getPieces(),
            'grossWeightKg'    => $parcel->getGrossWeightKg(),
            'declaredValue'    => $parcel->getDeclaredValue(),
            'declaredCurrency' => $parcel->getDeclaredCurrency(),
            'createdAt'        => $parcel->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updatedAt'        => $parcel->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }
}
```

- [ ] **Step 2: Verify PHP syntax**

```
php -l src/Module/Operations/Controller/ParcelController.php
```

Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add src/Module/Operations/Controller/ParcelController.php
git commit -m "feat: add ParcelController for Courier/Express (COU) sub-resource"
```

---

## Task 10: ShipmentLegController (Multimodal)

No new entity is needed — `Shipment.parentJobId` (already a plain `?int` column) links sub-leg shipments to their MMD parent. The controller lists child shipments by `parentJobId` and creates new sub-leg shipments with `parentJobId` set.

**Files:**
- Create: `src/Module/Operations/Controller/ShipmentLegController.php`

First, verify the existing Shipment entity has the `parentJobId` property and its getters/setters:

```
grep -n "parentJobId\|getParentJobId\|setParentJobId" src/Module/Operations/Entity/Shipment.php
```

Expected output: lines showing `private ?int $parentJobId = null;`, `getParentJobId()`, and `setParentJobId()`.

Also verify what fields Shipment has for mode and service type — check if `transportMode`/`serviceType` exist, or if the frontend sets them via a different field:

```
grep -n "transportMode\|serviceType\|transport_mode\|service_type" src/Module/Operations/Entity/Shipment.php
```

Use the results to decide which fields to copy when creating a sub-leg. If the Shipment entity does NOT have `transportMode` / `serviceType` columns, use whatever mode/service field it does have.

- [ ] **Step 1: Create the controller**

```php
<?php
namespace App\Module\Operations\Controller;

use App\Misc\Attribute\AppModule;
use App\Module\Operations\Entity\Shipment;
use App\Module\Operations\Repository\ShipmentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/shipment/{shipmentId}/legs')]
#[IsGranted('ROLE_USER')]
#[AppModule('operations')]
class ShipmentLegController extends AbstractController
{
    public function __construct(
        private readonly ShipmentRepository $shipmentRepository,
    ) {}

    #[Route('', methods: ['GET'])]
    public function list(int $shipmentId): JsonResponse
    {
        $parent = $this->shipmentRepository->find($shipmentId);
        if (!$parent) throw $this->createNotFoundException();

        $legs = $this->shipmentRepository->findBy(
            ['parentJobId' => $shipmentId],
            ['id' => 'ASC']
        );

        return $this->json(array_map(fn($leg) => $this->serializeLeg($leg), $legs));
    }

    #[Route('', methods: ['POST'])]
    public function create(int $shipmentId, Request $request): JsonResponse
    {
        $parent = $this->shipmentRepository->find($shipmentId);
        if (!$parent) throw $this->createNotFoundException();

        $body = json_decode($request->getContent(), true) ?? [];

        $leg = new Shipment();
        $leg->setParentJobId($shipmentId);

        if (isset($body['transportMode'])) $leg->setTransportMode($body['transportMode']);
        if (isset($body['serviceType']))   $leg->setServiceType($body['serviceType']);
        if (isset($body['code']))          $leg->setCode($body['code']);
        if (isset($body['note']))          $leg->setNote($body['note']);

        $this->shipmentRepository->save($leg);
        return $this->json($this->serializeLeg($leg), Response::HTTP_CREATED);
    }

    #[Route('/{legId}', methods: ['DELETE'])]
    public function delete(int $shipmentId, int $legId): JsonResponse
    {
        $leg = $this->shipmentRepository->find($legId);
        if (!$leg || $leg->getParentJobId() !== $shipmentId) throw $this->createNotFoundException();

        $this->shipmentRepository->delete($leg);
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    private function serializeLeg(Shipment $leg): array
    {
        return [
            'id'            => $leg->getId(),
            'parentJobId'   => $leg->getParentJobId(),
            'transportMode' => method_exists($leg, 'getTransportMode') ? $leg->getTransportMode() : null,
            'serviceType'   => method_exists($leg, 'getServiceType') ? $leg->getServiceType() : null,
            'code'          => method_exists($leg, 'getCode') ? $leg->getCode() : null,
        ];
    }
}
```

**Critical:** After writing this file, run the grep commands above to verify which getter/setter names Shipment actually uses for `parentJobId`, `transportMode`, `serviceType`, and `code`. If the method names are different, adjust the controller before committing.

- [ ] **Step 2: Verify PHP syntax**

```
php -l src/Module/Operations/Controller/ShipmentLegController.php
```

Expected: `No syntax errors detected`

- [ ] **Step 3: Run tests to make sure nothing broke**

```
php bin/phpunit --testdox 2>&1 | tail -20
```

Expected: tests pass or pre-existing failures only (no new failures introduced).

- [ ] **Step 4: Commit**

```bash
git add src/Module/Operations/Controller/ShipmentLegController.php
git commit -m "feat: add ShipmentLegController for Multimodal (MMD) sub-leg management"
```

---

## Task 11: Transport Modes Guide

**Files:**
- Create: `docs/guides/transport-modes.md`

- [ ] **Step 1: Write the guide**

```markdown
# Transport Modes Guide

The four additional transport modes — Road Freight (RD), Rail Freight (RAL), Courier/Express (COU), and Multimodal (MMD) — each extend the base Shipment record with mode-specific detail objects.

---

## Road Freight (RD) — Truck Details

For shipments with `transportMode = RD` and `serviceType = FTL`, create a Truck record linked to the shipment.

### Endpoints

#### List trucks for a shipment
```
GET /shipment/{shipmentId}/truck
Authorization: Bearer <token>
```

#### Create a truck record
```
POST /shipment/{shipmentId}/truck
Authorization: Bearer <token>
Content-Type: application/json

{
  "truckType":         "CURTAINSIDER",   (required) BOX|CURTAINSIDER|FLATBED|REEFER|TANKER
  "payloadKg":         5000,             (optional)
  "truckPlate":        "51A-12345",      (optional)
  "driverName":        "Nguyen Van A",   (optional)
  "haulierId":         42,               (optional) Provider ID of the haulier
  "pickupAddress":     "123 Main St",    (optional)
  "deliveryAddress":   "456 Port Rd",    (optional)
  "scheduledPickup":   "2026-07-01T08:00:00Z", (optional)
  "scheduledDelivery": "2026-07-02T17:00:00Z", (optional)
  "actualPickup":      null,             (optional)
  "actualDelivery":    null,             (optional)
  "podSignedBy":       null,             (optional)
  "podImageUrl":       null              (optional)
}
```

#### Update a truck record
```
PUT /shipment/{shipmentId}/truck/{truckId}
Authorization: Bearer <token>
Content-Type: application/json
```

#### Delete a truck record
```
DELETE /shipment/{shipmentId}/truck/{truckId}
Authorization: Bearer <token>
```

### Truck types

| Code | Description |
|---|---|
| `BOX` | Enclosed box truck |
| `CURTAINSIDER` | Side-curtain trailer — most common for general cargo |
| `FLATBED` | Open flatbed — heavy machinery, project cargo |
| `REEFER` | Temperature-controlled refrigerated truck |
| `TANKER` | Liquid/bulk tanker |

---

## Rail Freight (RAL) — Rail Booking Details

For shipments with `transportMode = RAL`, create a RailBooking record with ICD locations and CIM waybill information.

### Endpoints

#### List rail bookings for a shipment
```
GET /shipment/{shipmentId}/rail-booking
Authorization: Bearer <token>
```

#### Create a rail booking
```
POST /shipment/{shipmentId}/rail-booking
Authorization: Bearer <token>
Content-Type: application/json

{
  "trainService":    "CR Europe 1023",  (optional) Train service reference
  "departureIcd":   "CNCTU",           (optional) UN/LOCODE of origin ICD
  "arrivalIcd":     "DEHAM",           (optional) UN/LOCODE of destination ICD
  "operator":       "DB Cargo",        (optional) Rail operator name
  "cimWaybillNumber": "CIM-2026-0012", (optional) CIM consignment note number
  "cimWaybillDate": "2026-07-01",      (optional) Date issued (YYYY-MM-DD)
  "departureDate":  "2026-07-05T18:00:00Z", (optional)
  "arrivalDate":    "2026-07-19T10:00:00Z", (optional) ~14 days China→Europe
  "containerCount": 2,                 (optional) Number of containers on this train service
  "note":           null               (optional)
}
```

#### Update a rail booking
```
PUT /shipment/{shipmentId}/rail-booking/{rbId}
```

#### Delete a rail booking
```
DELETE /shipment/{shipmentId}/rail-booking/{rbId}
```

### CIM Waybill

The CIM (Convention Internationale concernant le transport des Marchandises par chemin de fer) is the international rail consignment note. It is the rail equivalent of the Bill of Lading for ocean or the AWB for air. Record the waybill number as soon as it is issued by the rail operator.

### ICD Codes

Use UN/LOCODE codes for ICDs (Inland Container Depots), the same format used for port codes. Examples:
- `CNCTU` — Chengdu ICD, China
- `CNXFW` — Xi'an ICD, China
- `PLMWR` — Małaszewicze, Poland (main China-EU border crossing)
- `DEHAM` — Hamburg, Germany

---

## Courier / Express (COU) — Parcel Details

For shipments with `transportMode = COU`, create one or more Parcel records — one per physical parcel or tracking number.

### Endpoints

#### List parcels for a shipment
```
GET /shipment/{shipmentId}/parcel
Authorization: Bearer <token>
```

#### Create a parcel record
```
POST /shipment/{shipmentId}/parcel
Authorization: Bearer <token>
Content-Type: application/json

{
  "serviceLevel":     "EXPRESS",        (required) ECONOMY|EXPRESS|OVERNIGHT|SAME-DAY
  "grossWeightKg":    2.5,              (required, > 0)
  "pieces":           1,                (optional, default 1)
  "trackingNumber":   "1Z999AA10123456784", (optional) integrator tracking number
  "integrator":       "UPS",            (optional) FEDEX|DHL|UPS|TNT
  "declaredValue":    150.00,           (optional) customs declared value
  "declaredCurrency": "USD"             (optional) ISO 4217 currency of declared value
}
```

#### Update a parcel record
```
PUT /shipment/{shipmentId}/parcel/{parcelId}
```

#### Delete a parcel record
```
DELETE /shipment/{shipmentId}/parcel/{parcelId}
```

### Service levels

| Code | Typical transit |
|---|---|
| `ECONOMY` | 3–7 business days |
| `EXPRESS` | 1–3 business days |
| `OVERNIGHT` | Next business day |
| `SAME-DAY` | Same-day delivery within a city or region |

### Chargeable weight (IATA volumetric)

For courier shipments, the chargeable weight is `MAX(grossWeightKg, (L×W×H cm³) / 6000)`. Carriers bill on whichever is higher — actual weight or volumetric weight.

---

## Multimodal (MMD) — Sub-Leg Management

A Multimodal shipment is a parent job that aggregates two or more transport legs under one contract and one document (MTD — Multimodal Transport Document). Each sub-leg is a child Shipment linked via `parentJobId`.

### How it works

1. Create the parent MMD shipment normally with `transportMode = MMD`.
2. Add sub-legs via `POST /shipment/{shipmentId}/legs`. Each sub-leg is itself a Shipment with its own `transportMode` (e.g. OCN, RAL, RD) and `serviceType`.
3. For each sub-leg, add the appropriate mode-specific detail record (truck, rail-booking, parcel, etc.) using the sub-leg's shipment ID.

### Endpoints

#### List sub-legs
```
GET /shipment/{shipmentId}/legs
Authorization: Bearer <token>
```

Returns an array of child shipment objects.

#### Add a sub-leg
```
POST /shipment/{shipmentId}/legs
Authorization: Bearer <token>
Content-Type: application/json

{
  "transportMode": "RAL",       (required) The mode for this leg
  "serviceType":   "FCL-RAIL",  (required) The service type for this leg
  "code":          "SHP-2026-0012-LEG2"  (optional) Internal reference for this leg
}
```

#### Delete a sub-leg
```
DELETE /shipment/{shipmentId}/legs/{legId}
```

### Example: China → Europe SEA-RAIL-ROAD

```
MMD Parent (shipmentId=100):
  transportMode: MMD
  serviceType: SEA-RAIL-ROAD

Sub-leg 1 (shipmentId=101, parentJobId=100):
  transportMode: OCN, serviceType: FCL
  → POST /shipment/101/booking   (vessel + voyage)

Sub-leg 2 (shipmentId=102, parentJobId=100):
  transportMode: RAL, serviceType: FCL-RAIL
  → POST /shipment/102/rail-booking   (ICD + CIM waybill)

Sub-leg 3 (shipmentId=103, parentJobId=100):
  transportMode: RD, serviceType: FTL
  → POST /shipment/103/truck   (truck + driver)
```
```

- [ ] **Step 2: Verify the file was written**

```
wc -l docs/guides/transport-modes.md
```

Expected: > 150 lines

- [ ] **Step 3: Commit**

```bash
git add docs/guides/transport-modes.md
git commit -m "docs: add transport-modes guide (RD, RAL, COU, MMD)"
```

---

## Spec Self-Review

**Spec coverage:**
- Road Freight / FTL truck details → Tasks 1–3 ✅
- Rail Freight / CIM waybill / ICD-to-ICD routes → Tasks 4–6 ✅
- Courier / Express / integrator tracking numbers → Tasks 7–9 ✅
- Multimodal sub-legs → Task 10 ✅
- Guide → Task 11 ✅

**Type consistency:**
- `TruckRepository.findByShipment()` → used in `TruckController.list()` ✅
- `RailBookingRepository.findByShipment()` → used in `RailBookingController.list()` ✅
- `ParcelRepository.findByShipment()` → used in `ParcelController.list()` ✅
- `ShipmentRepository.findBy(['parentJobId' => ...])` → uses the Doctrine `findBy` built-in (no custom method needed) ✅
- All migration versions are sequential: 170000, 180000, 190000 (no conflicts with existing 160000) ✅
- All entity datetime fields use `\DateTimeInterface` in PHP and `DATETIME` in SQL ✅

**Known adaptation point:** Task 10 includes a required grep step to confirm exact getter/setter names on `Shipment` for `parentJobId`, `transportMode`, `serviceType`, and `code` before finalizing that controller.
