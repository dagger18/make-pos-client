# Customer Portal Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a self-service customer portal where shippers/consignees view their shipments, download documents, view AR invoices, and submit quote requests — filtered to their client organisation.

**Architecture:** `Client` entity = customer organisation. `PortalUser` is a separate entity from the internal `User`, linked to a `Client` and authenticated via a dedicated Symfony firewall (`/portal/` prefix). All portal API responses filter by `portalUser.client`, the portal BO lives in `src/pages/portal/` within the existing Vue app. Milestones and documents gain customer-visibility controls. Signed 15-minute download URLs protect document files.

**Tech Stack:** PHP 8.2 / Symfony 7, Doctrine ORM (mysql + sqlite dual migrations), Vue 3 + Vuetify, unplugin-pages file-based routing.

**Out of scope:** Payment gateway integration, on-site reporting, booking requests, 2FA.

---

## File Map

### Client API (`d:\Projects\make-cargo-client`)
| Action | Path |
|--------|------|
| Modify | `src/Misc/Enum/MilestoneCode.php` — add `customerLabel()` + `isCustomerVisible()` |
| Modify | `src/Entity/ShipmentDocument.php` — add `isCustomerAccessible` bool |
| Modify | `src/Entity/ShipmentActivity.php` — add `source` string |
| Create | `migrations/mysql/Version20260624140000.php` + sqlite — `portal_user` |
| Create | `migrations/mysql/Version20260624150000.php` + sqlite — `portal_token` |
| Create | `migrations/mysql/Version20260624160000.php` + sqlite — `portal_quote_request` |
| Create | `migrations/mysql/Version20260624170000.php` + sqlite — ALTER `shipment_document` |
| Create | `migrations/mysql/Version20260624180000.php` + sqlite — ALTER `shipment_activity` |
| Create | `src/Entity/PortalUser.php` |
| Create | `src/Entity/PortalToken.php` |
| Create | `src/Entity/PortalQuoteRequest.php` |
| Create | `src/Repository/PortalUserRepository.php` |
| Create | `src/Repository/PortalTokenRepository.php` |
| Create | `src/Repository/PortalQuoteRequestRepository.php` |
| Create | `config/serializer_groups/PortalUser.yaml` |
| Create | `config/serializer_groups/PortalQuoteRequest.yaml` |
| Create | `src/Security/PortalAuthenticator.php` |
| Create | `src/Security/PortalUserProvider.php` |
| Modify | `config/packages/security.yaml` — add portal firewall + provider |
| Create | `src/Service/PortalAuthService.php` |
| Create | `src/Service/PortalShipmentService.php` |
| Create | `src/Service/PortalDocumentService.php` |
| Create | `src/Service/PortalInvoiceService.php` |
| Create | `src/Service/PortalQuoteRequestService.php` |
| Modify | `config/services.yaml` — register 5 new services |
| Create | `src/Controller/Portal/PortalAuthController.php` |
| Create | `src/Controller/Portal/PortalShipmentController.php` |
| Create | `src/Controller/Portal/PortalDocumentController.php` |
| Create | `src/Controller/Portal/PortalInvoiceController.php` |
| Create | `src/Controller/Portal/PortalQuoteRequestController.php` |

### Client BO (`d:\Projects\make-cargo-client-bo`)
| Action | Path |
|--------|------|
| Create | `src/layouts/portal.vue` |
| Create | `src/stores/portalAuthStore.js` |
| Create | `src/services/portal/PortalAuthService.js` |
| Create | `src/services/portal/PortalShipmentService.js` |
| Create | `src/services/portal/PortalDocumentService.js` |
| Create | `src/services/portal/PortalInvoiceService.js` |
| Create | `src/services/portal/PortalQuoteRequestService.js` |
| Create | `src/pages/portal/login.vue` |
| Create | `src/pages/portal/dashboard.vue` |
| Create | `src/pages/portal/shipments.vue` |
| Create | `src/pages/portal/shipments/[id].vue` |
| Create | `src/pages/portal/documents.vue` |
| Create | `src/pages/portal/invoices.vue` |
| Create | `src/pages/portal/quote-request.vue` |
| Modify | `src/router/index.js` (or wherever route guards live) — add portal auth guard |

---

## Task 1: MilestoneCode Customer Labels + Document & Activity Flags

**Files:**
- Modify: `d:\Projects\make-cargo-client\src\Misc\Enum\MilestoneCode.php`
- Modify: `d:\Projects\make-cargo-client\src\Entity\ShipmentDocument.php`
- Modify: `d:\Projects\make-cargo-client\src\Entity\ShipmentActivity.php`
- Create: `d:\Projects\make-cargo-client\migrations\mysql\Version20260624170000.php`
- Create: `d:\Projects\make-cargo-client\migrations\sqlite\Version20260624170000.php`
- Create: `d:\Projects\make-cargo-client\migrations\mysql\Version20260624180000.php`
- Create: `d:\Projects\make-cargo-client\migrations\sqlite\Version20260624180000.php`

- [ ] **Step 1: Add `customerLabel()` and `isCustomerVisible()` to `MilestoneCode` enum**

Add these two methods to the existing enum in `src/Misc/Enum/MilestoneCode.php`. Do NOT remove any existing methods; add below `description()`:

```php
public function customerLabel(): string
{
    return match($this) {
        self::CargoBooked     => 'Booking confirmed',
        self::CargoReady      => 'Cargo ready for collection',
        self::GateIn          => 'Cargo received at port',
        self::OnBoard         => 'Cargo loaded on vessel',
        self::VesselDeparted  => 'Vessel departed',
        self::AtTransshipment => 'At transshipment port',
        self::VesselArrived   => 'Vessel arrived at destination',
        self::Discharged      => 'Cargo discharged',
        self::CustomsReleased => 'Customs cleared',
        self::Available       => 'Available for pickup',
        self::Delivered       => 'Cargo delivered',
        self::CargoAccepted   => 'Cargo accepted at airport',
        self::FlightDeparted  => 'Flight departed',
        self::FlightArrived   => 'Flight arrived',
        self::PickupCompleted => 'Cargo collected',
        self::InTransit       => 'In transit',
        default               => '',
    };
}

public function isCustomerVisible(): bool
{
    return match($this) {
        self::CargoBooked,
        self::CargoReady,
        self::GateIn,
        self::OnBoard,
        self::VesselDeparted,
        self::AtTransshipment,
        self::VesselArrived,
        self::Discharged,
        self::CustomsReleased,
        self::Available,
        self::Delivered,
        self::CargoAccepted,
        self::FlightDeparted,
        self::FlightArrived,
        self::PickupCompleted,
        self::InTransit       => true,
        default               => false,
    };
}
```

- [ ] **Step 2: Add `isCustomerAccessible` to `ShipmentDocument` entity**

In `src/Entity/ShipmentDocument.php`, add the field and getter/setter after the existing fields (before the closing brace):

```php
#[ORM\Column]
private bool $isCustomerAccessible = false;

public function isCustomerAccessible(): bool { return $this->isCustomerAccessible; }
public function setIsCustomerAccessible(bool $v): static { $this->isCustomerAccessible = $v; return $this; }
```

- [ ] **Step 3: Add `source` to `ShipmentActivity` entity**

In `src/Entity/ShipmentActivity.php`, add the field and getter/setter:

```php
#[ORM\Column(length: 16, nullable: true)]
private ?string $source = 'INTERNAL';

public function getSource(): ?string { return $this->source; }
public function setSource(?string $v): static { $this->source = $v; return $this; }
```

- [ ] **Step 4: Create MySQL migration for `shipment_document.is_customer_accessible` — `migrations/mysql/Version20260624170000.php`**

```php
<?php
declare(strict_types=1);
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260624170000 extends AbstractMigration
{
    public function getDescription(): string { return 'Add is_customer_accessible to shipment_document'; }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shipment_document ADD COLUMN is_customer_accessible TINYINT(1) NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shipment_document DROP COLUMN is_customer_accessible');
    }
}
```

- [ ] **Step 5: Create SQLite migration — `migrations/sqlite/Version20260624170000.php`**

```php
<?php
declare(strict_types=1);
namespace SqlEngineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260624170000 extends AbstractMigration
{
    public function getDescription(): string { return 'Add is_customer_accessible to shipment_document'; }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shipment_document ADD COLUMN is_customer_accessible INTEGER NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shipment_document DROP COLUMN is_customer_accessible');
    }
}
```

- [ ] **Step 6: Create MySQL migration for `shipment_activity.source` — `migrations/mysql/Version20260624180000.php`**

```php
<?php
declare(strict_types=1);
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260624180000 extends AbstractMigration
{
    public function getDescription(): string { return 'Add source column to shipment_activity'; }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE shipment_activity ADD COLUMN source VARCHAR(16) NOT NULL DEFAULT 'INTERNAL'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shipment_activity DROP COLUMN source');
    }
}
```

- [ ] **Step 7: Create SQLite migration — `migrations/sqlite/Version20260624180000.php`**

```php
<?php
declare(strict_types=1);
namespace SqlEngineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260624180000 extends AbstractMigration
{
    public function getDescription(): string { return 'Add source column to shipment_activity'; }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE shipment_activity ADD COLUMN source VARCHAR(16) NOT NULL DEFAULT 'INTERNAL'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shipment_activity DROP COLUMN source');
    }
}
```

- [ ] **Step 8: Commit**

```
git add src/Misc/Enum/MilestoneCode.php src/Entity/ShipmentDocument.php src/Entity/ShipmentActivity.php
git add migrations/mysql/Version20260624170000.php migrations/sqlite/Version20260624170000.php
git add migrations/mysql/Version20260624180000.php migrations/sqlite/Version20260624180000.php
git commit -m "feat: add customer-visible milestone labels, document accessibility flag, activity source"
```
_(Run in `d:\Projects\make-cargo-client`)_

---

## Task 2: PortalUser + PortalToken Entities, Repositories, Migrations

**Files:**
- Create: `src/Entity/PortalUser.php`
- Create: `src/Entity/PortalToken.php`
- Create: `src/Repository/PortalUserRepository.php`
- Create: `src/Repository/PortalTokenRepository.php`
- Create: `migrations/mysql/Version20260624140000.php` + sqlite
- Create: `migrations/mysql/Version20260624150000.php` + sqlite
- Create: `config/serializer_groups/PortalUser.yaml`

- [ ] **Step 1: Create `src/Entity/PortalUser.php`**

`PortalUser` implements `UserInterface` so Symfony security can load it from the portal firewall. It is NOT the same as internal `User`.

```php
<?php
namespace App\Entity;

use App\Repository\PortalUserRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: PortalUserRepository::class)]
#[ORM\HasLifecycleCallbacks]
class PortalUser implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Client $client = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Contact $contact = null;

    #[ORM\Column(length: 128, unique: true)]
    private string $email;

    #[ORM\Column(length: 255)]
    private string $passwordHash;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column(length: 16)]
    private string $role = 'VIEWER';

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $lastLoginAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTime();
    }

    // Symfony UserInterface
    public function getRoles(): array { return ['ROLE_PORTAL_USER']; }
    public function getPassword(): ?string { return $this->passwordHash; }
    public function getUserIdentifier(): string { return $this->email; }
    public function eraseCredentials(): void {}

    public function getId(): ?int { return $this->id; }
    public function getClient(): ?Client { return $this->client; }
    public function setClient(?Client $v): static { $this->client = $v; return $this; }
    public function getContact(): ?Contact { return $this->contact; }
    public function setContact(?Contact $v): static { $this->contact = $v; return $this; }
    public function getEmail(): string { return $this->email; }
    public function setEmail(string $v): static { $this->email = $v; return $this; }
    public function getPasswordHash(): string { return $this->passwordHash; }
    public function setPasswordHash(string $v): static { $this->passwordHash = $v; return $this; }
    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $v): static { $this->isActive = $v; return $this; }
    public function getRole(): string { return $this->role; }
    public function setRole(string $v): static { $this->role = $v; return $this; }
    public function getLastLoginAt(): ?\DateTimeInterface { return $this->lastLoginAt; }
    public function setLastLoginAt(?\DateTimeInterface $v): static { $this->lastLoginAt = $v; return $this; }
    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
}
```

- [ ] **Step 2: Create `src/Entity/PortalToken.php`**

```php
<?php
namespace App\Entity;

use App\Repository\PortalTokenRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PortalTokenRepository::class)]
class PortalToken
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?PortalUser $portalUser = null;

    #[ORM\Column(length: 64, unique: true)]
    private string $token;

    #[ORM\Column]
    private int $expiresAt;

    public function getId(): ?int { return $this->id; }
    public function getPortalUser(): ?PortalUser { return $this->portalUser; }
    public function setPortalUser(?PortalUser $v): static { $this->portalUser = $v; return $this; }
    public function getToken(): string { return $this->token; }
    public function setToken(string $v): static { $this->token = $v; return $this; }
    public function getExpiresAt(): int { return $this->expiresAt; }
    public function setExpiresAt(int $v): static { $this->expiresAt = $v; return $this; }
    public function isExpired(): bool { return time() > $this->expiresAt; }
}
```

- [ ] **Step 3: Create `src/Repository/PortalUserRepository.php`**

Extends `BaseRepository` like all other repositories in this project.

```php
<?php
namespace App\Repository;

use App\Entity\PortalUser;

class PortalUserRepository extends BaseRepository
{
    public function findByEmail(string $email): ?PortalUser
    {
        return $this->findOneBy(['email' => $email]);
    }

    public function save(PortalUser $entity): PortalUser
    {
        $em = $this->getEntityManager();
        $em->persist($entity);
        $em->flush();
        return $entity;
    }
}
```

- [ ] **Step 4: Create `src/Repository/PortalTokenRepository.php`**

```php
<?php
namespace App\Repository;

use App\Entity\PortalToken;
use App\Entity\PortalUser;

class PortalTokenRepository extends BaseRepository
{
    public function findValidToken(string $token): ?PortalToken
    {
        return $this->createQueryBuilder('t')
            ->where('t.token = :token')
            ->andWhere('t.expiresAt > :now')
            ->setParameter('token', $token)
            ->setParameter('now', time())
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function save(PortalToken $entity): PortalToken
    {
        $em = $this->getEntityManager();
        $em->persist($entity);
        $em->flush();
        return $entity;
    }

    public function deleteByUser(PortalUser $user): void
    {
        $this->createQueryBuilder('t')
            ->delete()
            ->where('t.portalUser = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }
}
```

- [ ] **Step 5: Create MySQL migration — `migrations/mysql/Version20260624140000.php`**

```php
<?php
declare(strict_types=1);
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260624140000 extends AbstractMigration
{
    public function getDescription(): string { return 'Create portal_user table'; }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE portal_user (id INT NOT NULL AUTO_INCREMENT, client_id INT NOT NULL, contact_id INT DEFAULT NULL, email VARCHAR(128) NOT NULL, password_hash VARCHAR(255) NOT NULL, is_active TINYINT(1) NOT NULL DEFAULT 1, role VARCHAR(16) NOT NULL DEFAULT \'VIEWER\', last_login_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id), UNIQUE INDEX UNIQ_portal_user_email (email), CONSTRAINT FK_portal_user_client FOREIGN KEY (client_id) REFERENCES client (id) ON DELETE CASCADE, CONSTRAINT FK_portal_user_contact FOREIGN KEY (contact_id) REFERENCES contact (id) ON DELETE SET NULL) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void { $this->addSql('DROP TABLE portal_user'); }
}
```

- [ ] **Step 6: Create SQLite migration — `migrations/sqlite/Version20260624140000.php`**

```php
<?php
declare(strict_types=1);
namespace SqlEngineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260624140000 extends AbstractMigration
{
    public function getDescription(): string { return 'Create portal_user table'; }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE portal_user (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, client_id INTEGER NOT NULL, contact_id INTEGER DEFAULT NULL, email VARCHAR(128) NOT NULL, password_hash VARCHAR(255) NOT NULL, is_active INTEGER NOT NULL DEFAULT 1, role VARCHAR(16) NOT NULL DEFAULT \'VIEWER\', last_login_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, CONSTRAINT UNIQ_portal_user_email UNIQUE (email), CONSTRAINT FK_portal_user_client FOREIGN KEY (client_id) REFERENCES client (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_portal_user_contact FOREIGN KEY (contact_id) REFERENCES contact (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
    }

    public function down(Schema $schema): void { $this->addSql('DROP TABLE portal_user'); }
}
```

- [ ] **Step 7: Create MySQL migration — `migrations/mysql/Version20260624150000.php`**

```php
<?php
declare(strict_types=1);
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260624150000 extends AbstractMigration
{
    public function getDescription(): string { return 'Create portal_token table'; }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE portal_token (id INT NOT NULL AUTO_INCREMENT, portal_user_id INT NOT NULL, token VARCHAR(64) NOT NULL, expires_at INT NOT NULL, PRIMARY KEY (id), UNIQUE INDEX UNIQ_portal_token (token), CONSTRAINT FK_portal_token_user FOREIGN KEY (portal_user_id) REFERENCES portal_user (id) ON DELETE CASCADE) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void { $this->addSql('DROP TABLE portal_token'); }
}
```

- [ ] **Step 8: Create SQLite migration — `migrations/sqlite/Version20260624150000.php`**

```php
<?php
declare(strict_types=1);
namespace SqlEngineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260624150000 extends AbstractMigration
{
    public function getDescription(): string { return 'Create portal_token table'; }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE portal_token (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, portal_user_id INTEGER NOT NULL, token VARCHAR(64) NOT NULL, expires_at INTEGER NOT NULL, CONSTRAINT UNIQ_portal_token UNIQUE (token), CONSTRAINT FK_portal_token_user FOREIGN KEY (portal_user_id) REFERENCES portal_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
    }

    public function down(Schema $schema): void { $this->addSql('DROP TABLE portal_token'); }
}
```

- [ ] **Step 9: Create `config/serializer_groups/PortalUser.yaml`**

```yaml
App\Entity\PortalUser:

    list:
        - id
        - email
        - role
        - isActive
        - lastLoginAt
        - createdAt

    detail:
        - id
        - email
        - role
        - isActive
        - lastLoginAt
        - createdAt
```

- [ ] **Step 10: Commit**

```
git add src/Entity/PortalUser.php src/Entity/PortalToken.php
git add src/Repository/PortalUserRepository.php src/Repository/PortalTokenRepository.php
git add migrations/mysql/Version20260624140000.php migrations/sqlite/Version20260624140000.php
git add migrations/mysql/Version20260624150000.php migrations/sqlite/Version20260624150000.php
git add config/serializer_groups/PortalUser.yaml
git commit -m "feat: add PortalUser and PortalToken entities and migrations"
```
_(Run in `d:\Projects\make-cargo-client`)_

---

## Task 3: PortalQuoteRequest Entity + Migration

**Files:**
- Create: `src/Entity/PortalQuoteRequest.php`
- Create: `src/Repository/PortalQuoteRequestRepository.php`
- Create: `migrations/mysql/Version20260624160000.php` + sqlite
- Create: `config/serializer_groups/PortalQuoteRequest.yaml`

- [ ] **Step 1: Create `src/Entity/PortalQuoteRequest.php`**

```php
<?php
namespace App\Entity;

use App\Misc\Traits\EntityDateTimeAbleTrait;
use App\Repository\PortalQuoteRequestRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PortalQuoteRequestRepository::class)]
#[ORM\HasLifecycleCallbacks]
class PortalQuoteRequest
{
    use EntityDateTimeAbleTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?PortalUser $portalUser = null;

    #[ORM\Column(length: 8, nullable: true)]
    private ?string $transportMode = null;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $serviceType = null;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $origin = null;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $destination = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $cargoDescription = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    private ?string $weightKg = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 4, nullable: true)]
    private ?string $volumeCbm = null;

    #[ORM\Column(length: 8, nullable: true)]
    private ?string $containerType = null;

    #[ORM\Column(length: 8, nullable: true)]
    private ?string $incoterm = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $cargoReadyDate = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $specialRequirements = null;

    #[ORM\Column(length: 16)]
    private string $status = 'RECEIVED';

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Quote $linkedQuote = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $assignedTo = null;

    public function getId(): ?int { return $this->id; }
    public function getPortalUser(): ?PortalUser { return $this->portalUser; }
    public function setPortalUser(?PortalUser $v): static { $this->portalUser = $v; return $this; }
    public function getTransportMode(): ?string { return $this->transportMode; }
    public function setTransportMode(?string $v): static { $this->transportMode = $v; return $this; }
    public function getServiceType(): ?string { return $this->serviceType; }
    public function setServiceType(?string $v): static { $this->serviceType = $v; return $this; }
    public function getOrigin(): ?string { return $this->origin; }
    public function setOrigin(?string $v): static { $this->origin = $v; return $this; }
    public function getDestination(): ?string { return $this->destination; }
    public function setDestination(?string $v): static { $this->destination = $v; return $this; }
    public function getCargoDescription(): ?string { return $this->cargoDescription; }
    public function setCargoDescription(?string $v): static { $this->cargoDescription = $v; return $this; }
    public function getWeightKg(): ?string { return $this->weightKg; }
    public function setWeightKg(?string $v): static { $this->weightKg = $v; return $this; }
    public function getVolumeCbm(): ?string { return $this->volumeCbm; }
    public function setVolumeCbm(?string $v): static { $this->volumeCbm = $v; return $this; }
    public function getContainerType(): ?string { return $this->containerType; }
    public function setContainerType(?string $v): static { $this->containerType = $v; return $this; }
    public function getIncoterm(): ?string { return $this->incoterm; }
    public function setIncoterm(?string $v): static { $this->incoterm = $v; return $this; }
    public function getCargoReadyDate(): ?\DateTimeInterface { return $this->cargoReadyDate; }
    public function setCargoReadyDate(?\DateTimeInterface $v): static { $this->cargoReadyDate = $v; return $this; }
    public function getSpecialRequirements(): ?string { return $this->specialRequirements; }
    public function setSpecialRequirements(?string $v): static { $this->specialRequirements = $v; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $v): static { $this->status = $v; return $this; }
    public function getLinkedQuote(): ?Quote { return $this->linkedQuote; }
    public function setLinkedQuote(?Quote $v): static { $this->linkedQuote = $v; return $this; }
    public function getAssignedTo(): ?User { return $this->assignedTo; }
    public function setAssignedTo(?User $v): static { $this->assignedTo = $v; return $this; }
}
```

- [ ] **Step 2: Create `src/Repository/PortalQuoteRequestRepository.php`**

```php
<?php
namespace App\Repository;

use App\Entity\PortalQuoteRequest;
use App\Entity\PortalUser;

class PortalQuoteRequestRepository extends BaseRepository
{
    /** @return PortalQuoteRequest[] */
    public function findByPortalUser(PortalUser $user): array
    {
        return $this->createQueryBuilder('q')
            ->where('q.portalUser = :user')
            ->setParameter('user', $user)
            ->orderBy('q.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function save(PortalQuoteRequest $entity): PortalQuoteRequest
    {
        $em = $this->getEntityManager();
        $em->persist($entity);
        $em->flush();
        return $entity;
    }
}
```

- [ ] **Step 3: Create MySQL migration — `migrations/mysql/Version20260624160000.php`**

```php
<?php
declare(strict_types=1);
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260624160000 extends AbstractMigration
{
    public function getDescription(): string { return 'Create portal_quote_request table'; }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE portal_quote_request (id INT NOT NULL AUTO_INCREMENT, portal_user_id INT NOT NULL, linked_quote_id INT DEFAULT NULL, assigned_to_id INT DEFAULT NULL, transport_mode VARCHAR(8) DEFAULT NULL, service_type VARCHAR(16) DEFAULT NULL, origin VARCHAR(128) DEFAULT NULL, destination VARCHAR(128) DEFAULT NULL, cargo_description LONGTEXT DEFAULT NULL, weight_kg DECIMAL(12,2) DEFAULT NULL, volume_cbm DECIMAL(10,4) DEFAULT NULL, container_type VARCHAR(8) DEFAULT NULL, incoterm VARCHAR(8) DEFAULT NULL, cargo_ready_date DATE DEFAULT NULL, special_requirements LONGTEXT DEFAULT NULL, status VARCHAR(16) NOT NULL DEFAULT \'RECEIVED\', created_date DATETIME NOT NULL, updated_date DATETIME DEFAULT NULL, PRIMARY KEY (id), CONSTRAINT FK_pqr_portal_user FOREIGN KEY (portal_user_id) REFERENCES portal_user (id) ON DELETE CASCADE, CONSTRAINT FK_pqr_quote FOREIGN KEY (linked_quote_id) REFERENCES quote (id) ON DELETE SET NULL, CONSTRAINT FK_pqr_user FOREIGN KEY (assigned_to_id) REFERENCES user (id) ON DELETE SET NULL) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE INDEX IDX_pqr_portal_user ON portal_quote_request (portal_user_id)');
        $this->addSql('CREATE INDEX IDX_pqr_status ON portal_quote_request (status)');
    }

    public function down(Schema $schema): void { $this->addSql('DROP TABLE portal_quote_request'); }
}
```

- [ ] **Step 4: Create SQLite migration — `migrations/sqlite/Version20260624160000.php`**

```php
<?php
declare(strict_types=1);
namespace SqlEngineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260624160000 extends AbstractMigration
{
    public function getDescription(): string { return 'Create portal_quote_request table'; }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE portal_quote_request (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, portal_user_id INTEGER NOT NULL, linked_quote_id INTEGER DEFAULT NULL, assigned_to_id INTEGER DEFAULT NULL, transport_mode VARCHAR(8) DEFAULT NULL, service_type VARCHAR(16) DEFAULT NULL, origin VARCHAR(128) DEFAULT NULL, destination VARCHAR(128) DEFAULT NULL, cargo_description CLOB DEFAULT NULL, weight_kg NUMERIC(12,2) DEFAULT NULL, volume_cbm NUMERIC(10,4) DEFAULT NULL, container_type VARCHAR(8) DEFAULT NULL, incoterm VARCHAR(8) DEFAULT NULL, cargo_ready_date DATE DEFAULT NULL, special_requirements CLOB DEFAULT NULL, status VARCHAR(16) NOT NULL DEFAULT \'RECEIVED\', created_date DATETIME NOT NULL, updated_date DATETIME DEFAULT NULL, CONSTRAINT FK_pqr_portal_user FOREIGN KEY (portal_user_id) REFERENCES portal_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_pqr_quote FOREIGN KEY (linked_quote_id) REFERENCES quote (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_pqr_user FOREIGN KEY (assigned_to_id) REFERENCES user (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_pqr_portal_user ON portal_quote_request (portal_user_id)');
    }

    public function down(Schema $schema): void { $this->addSql('DROP TABLE portal_quote_request'); }
}
```

- [ ] **Step 5: Create `config/serializer_groups/PortalQuoteRequest.yaml`**

```yaml
App\Entity\PortalQuoteRequest:

    list:
        - id
        - transportMode
        - serviceType
        - origin
        - destination
        - status
        - cargoReadyDate
        - createdAt
        - updatedAt

    detail:
        - id
        - transportMode
        - serviceType
        - origin
        - destination
        - cargoDescription
        - weightKg
        - volumeCbm
        - containerType
        - incoterm
        - cargoReadyDate
        - specialRequirements
        - status
        - linkedQuote
        - assignedTo
        - createdAt
        - updatedAt
```

- [ ] **Step 6: Commit**

```
git add src/Entity/PortalQuoteRequest.php src/Repository/PortalQuoteRequestRepository.php
git add migrations/mysql/Version20260624160000.php migrations/sqlite/Version20260624160000.php
git add config/serializer_groups/PortalQuoteRequest.yaml
git commit -m "feat: add PortalQuoteRequest entity and migration"
```
_(Run in `d:\Projects\make-cargo-client`)_

---

## Task 4: Portal Security — Authenticator, User Provider, Firewall

**Files:**
- Create: `src/Security/PortalAuthenticator.php`
- Create: `src/Security/PortalUserProvider.php`
- Modify: `config/packages/security.yaml`

The portal uses the same `X-W-Auth: Token Email="...", Token="..."` header format as the internal API, but validates against the `portal_token` table instead of the `user_token` table.

- [ ] **Step 1: Create `src/Security/PortalUserProvider.php`**

```php
<?php
namespace App\Security;

use App\Repository\PortalUserRepository;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class PortalUserProvider implements UserProviderInterface
{
    public function __construct(
        private readonly PortalUserRepository $repository,
    ) {}

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $user = $this->repository->findByEmail($identifier);
        if (!$user) {
            throw new UserNotFoundException(sprintf('Portal user "%s" not found.', $identifier));
        }
        return $user;
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return $class === \App\Entity\PortalUser::class;
    }
}
```

- [ ] **Step 2: Create `src/Security/PortalAuthenticator.php`**

Mirrors `ApiAuthenticator` but looks up `PortalToken` and `PortalUser`. The X-W-Auth header format is `Token Email="user@example.com", Token="abc123"`.

```php
<?php
namespace App\Security;

use App\Repository\PortalTokenRepository;
use App\Repository\PortalUserRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class PortalAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly PortalUserRepository  $userRepository,
        private readonly PortalTokenRepository $tokenRepository,
    ) {}

    public function supports(Request $request): ?bool
    {
        return $request->headers->has('X-W-Auth')
            || ($request->query->has('YXV0aFRva2Vu') && $request->query->has('ZW1haWw'));
    }

    public function authenticate(Request $request): Passport
    {
        $auth = $request->headers->get('X-W-Auth', '');
        if ($auth) {
            preg_match('/Email="([^"]+)"/', $auth, $emailMatch);
            preg_match('/Token="([^"]+)"/', $auth, $tokenMatch);
            $email = $emailMatch[1] ?? '';
            $rawToken = $tokenMatch[1] ?? '';
        } else {
            $email    = base64_decode($request->query->get('ZW1haWw', ''));
            $rawToken = base64_decode($request->query->get('YXV0aFRva2Vu', ''));
        }

        if (!$email || !$rawToken) {
            throw new AuthenticationException('Missing credentials.');
        }

        $portalToken = $this->tokenRepository->findValidToken($rawToken);
        if (!$portalToken || $portalToken->getPortalUser()->getEmail() !== $email) {
            throw new AuthenticationException('Invalid or expired token.');
        }
        if (!$portalToken->getPortalUser()->isActive()) {
            throw new AuthenticationException('Portal account is inactive.');
        }

        return new SelfValidatingPassport(
            new UserBadge($email, fn() => $portalToken->getPortalUser())
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse(['error' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
    }
}
```

- [ ] **Step 3: Read `config/packages/security.yaml` then update it**

Read the file first (`d:\Projects\make-cargo-client\config\packages\security.yaml`), then make these changes:

**a) Under `providers:`, add a portal provider:**
```yaml
    portal_user_provider:
      id: App\Security\PortalUserProvider
```

**b) Under `firewalls:`, add TWO new firewalls BEFORE the existing `api_login` and `api` firewalls** (firewall order matters — Symfony uses the first match):
```yaml
    portal_login:
      pattern: ^/portal/auth
      security: false
    portal:
      pattern: ^/portal/
      stateless: true
      provider: portal_user_provider
      custom_authenticators:
        - App\Security\PortalAuthenticator
```

**c) Under `access_control:`, add BEFORE the existing `^/api` rules:**
```yaml
    - { path: ^/portal/auth, roles: PUBLIC_ACCESS }
    - { path: ^/portal, roles: ROLE_PORTAL_USER }
```

- [ ] **Step 4: Commit**

```
git add src/Security/PortalAuthenticator.php src/Security/PortalUserProvider.php config/packages/security.yaml
git commit -m "feat: add portal security firewall, authenticator, and user provider"
```
_(Run in `d:\Projects\make-cargo-client`)_

---

## Task 5: Portal API — Auth + Me Endpoints

**Files:**
- Create: `src/Controller/Portal/PortalAuthController.php`
- Create: `src/Service/PortalAuthService.php`
- Modify: `config/services.yaml`

- [ ] **Step 1: Create `src/Service/PortalAuthService.php`**

Does NOT extend BaseService (no repo needed — uses repositories directly).

```php
<?php
namespace App\Service;

use App\Entity\PortalToken;
use App\Entity\PortalUser;
use App\Repository\PortalTokenRepository;
use App\Repository\PortalUserRepository;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class PortalAuthService
{
    private const TOKEN_TTL_DAYS = 10;

    public function __construct(
        private readonly PortalUserRepository      $userRepository,
        private readonly PortalTokenRepository     $tokenRepository,
        private readonly UserPasswordHasherInterface $hasher,
    ) {}

    public function authenticate(string $email, string $password): ?PortalUser
    {
        $user = $this->userRepository->findByEmail($email);
        if (!$user || !$user->isActive()) {
            return null;
        }
        if (!$this->hasher->isPasswordValid($user, $password)) {
            return null;
        }
        $user->setLastLoginAt(new \DateTime());
        $this->userRepository->save($user);
        return $user;
    }

    public function createToken(PortalUser $user): PortalToken
    {
        $token = new PortalToken();
        $token->setPortalUser($user);
        $token->setToken(bin2hex(random_bytes(32)));
        $token->setExpiresAt(time() + (self::TOKEN_TTL_DAYS * 86400));
        return $this->tokenRepository->save($token);
    }

    public function createUser(array $data): PortalUser
    {
        $user = new PortalUser();
        $user->setEmail(strtolower(trim($data['email'])));
        $user->setPasswordHash($this->hasher->hashPassword($user, $data['password']));
        $user->setRole($data['role'] ?? 'VIEWER');
        if (isset($data['client'])) {
            $user->setClient($data['client']);
        }
        if (isset($data['contact'])) {
            $user->setContact($data['contact']);
        }
        return $this->userRepository->save($user);
    }

    public function logout(PortalUser $user): void
    {
        $this->tokenRepository->deleteByUser($user);
    }
}
```

- [ ] **Step 2: Create `src/Controller/Portal/PortalAuthController.php`**

```php
<?php
namespace App\Controller\Portal;

use App\Service\PortalAuthService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/portal')]
class PortalAuthController extends AbstractController
{
    public function __construct(
        private readonly PortalAuthService $authService,
    ) {}

    #[Route('/auth', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $body = json_decode($request->getContent(), true) ?? [];
        $email    = trim($body['email'] ?? '');
        $password = $body['password'] ?? '';

        if (!$email || !$password) {
            return $this->json(['error' => 'Email and password are required.'], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->authService->authenticate($email, $password);
        if (!$user) {
            return $this->json(['error' => 'Invalid credentials.'], Response::HTTP_UNAUTHORIZED);
        }

        $token = $this->authService->createToken($user);

        return $this->json([
            'accessToken' => $token->getToken(),
            'user' => [
                'id'    => $user->getId(),
                'email' => $user->getEmail(),
                'role'  => $user->getRole(),
            ],
        ]);
    }

    #[Route('/me', methods: ['GET'])]
    public function me(#[CurrentUser] $user): JsonResponse
    {
        /** @var \App\Entity\PortalUser $user */
        $client = $user->getClient();
        return $this->json([
            'id'       => $user->getId(),
            'email'    => $user->getEmail(),
            'role'     => $user->getRole(),
            'clientId' => $client?->getId(),
            'clientName' => $client?->getName(),
        ]);
    }

    #[Route('/logout', methods: ['POST'])]
    public function logout(#[CurrentUser] $user): JsonResponse
    {
        /** @var \App\Entity\PortalUser $user */
        $this->authService->logout($user);
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
```

- [ ] **Step 3: Register `PortalAuthService` in `config/services.yaml`**

In `app.auto_service_locator` arguments, after the tracking services, add:
```yaml
                App\Service\PortalAuthService: '@App\Service\PortalAuthService'
                App\Service\PortalShipmentService: '@App\Service\PortalShipmentService'
                App\Service\PortalDocumentService: '@App\Service\PortalDocumentService'
                App\Service\PortalInvoiceService: '@App\Service\PortalInvoiceService'
                App\Service\PortalQuoteRequestService: '@App\Service\PortalQuoteRequestService'
```

Note: `PortalAuthService` does NOT extend `BaseService`, so it won't be auto-wired into the locator — but registering it here is fine for consistency. Services that DO extend `BaseService` also need to be registered.

- [ ] **Step 4: Commit**

```
git add src/Service/PortalAuthService.php src/Controller/Portal/PortalAuthController.php config/services.yaml
git commit -m "feat: add portal auth controller and service (login/logout/me)"
```
_(Run in `d:\Projects\make-cargo-client`)_

---

## Task 6: Portal API — Shipments, Documents, Invoices, Quote Requests

**Files:**
- Create: `src/Service/PortalShipmentService.php`
- Create: `src/Service/PortalDocumentService.php`
- Create: `src/Service/PortalInvoiceService.php`
- Create: `src/Service/PortalQuoteRequestService.php`
- Create: `src/Controller/Portal/PortalShipmentController.php`
- Create: `src/Controller/Portal/PortalDocumentController.php`
- Create: `src/Controller/Portal/PortalInvoiceController.php`
- Create: `src/Controller/Portal/PortalQuoteRequestController.php`

All portal controllers use `#[IsGranted('ROLE_PORTAL_USER')]` and inject `#[CurrentUser] $user` to get the authenticated `PortalUser`.

**Key data access rule:** filter everything by `$user->getClient()`. Never expose buy rates, AP invoices, or cost data.

**AR invoice filter:** `EbitNoteType::InvoiceDebit` (`'ID'`) — these are revenue invoices sent to customers. Exclude types `InvoiceCredit ('IC')`, `POBO`, `COBO`, `RecordReceipt`, `RecordPayment`.

- [ ] **Step 1: Create `src/Service/PortalShipmentService.php`**

```php
<?php
namespace App\Service;

use App\Entity\Client;
use App\Repository\ShipmentRepository;

class PortalShipmentService
{
    public function __construct(
        private readonly ShipmentRepository $shipmentRepository,
    ) {}

    public function getShipmentsForClient(Client $client, int $limit = 50, int $offset = 0): array
    {
        return $this->shipmentRepository->createQueryBuilder('s')
            ->innerJoin('s.parties', 'p')
            ->where('p.client = :client')
            ->setParameter('client', $client)
            ->orderBy('s.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    public function getShipmentForClient(int $id, Client $client): ?object
    {
        return $this->shipmentRepository->createQueryBuilder('s')
            ->innerJoin('s.parties', 'p')
            ->where('s.id = :id')
            ->andWhere('p.client = :client')
            ->setParameter('id', $id)
            ->setParameter('client', $client)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
```

- [ ] **Step 2: Create `src/Service/PortalDocumentService.php`**

Generates signed download URLs. The signature is a 15-minute HMAC using the `APP_SECRET` parameter (Symfony's kernel secret).

```php
<?php
namespace App\Service;

use App\Entity\Client;
use App\Repository\ShipmentDocumentRepository;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class PortalDocumentService
{
    public function __construct(
        private readonly ShipmentDocumentRepository $documentRepository,
        private readonly ParameterBagInterface      $params,
    ) {}

    public function getDocumentsForClient(Client $client): array
    {
        return $this->documentRepository->createQueryBuilder('d')
            ->innerJoin('d.shipment', 's')
            ->innerJoin('s.parties', 'p')
            ->where('p.client = :client')
            ->andWhere('d.isCustomerAccessible = true')
            ->setParameter('client', $client)
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function generateDownloadUrl(int $documentId, string $baseUrl): string
    {
        $expires = time() + 900; // 15 minutes
        $sig = hash_hmac('sha256', "portal_doc:{$documentId}:{$expires}", $this->getSecret());
        return rtrim($baseUrl, '/') . "/portal/documents/{$documentId}/file?expires={$expires}&sig={$sig}";
    }

    public function validateDownloadSignature(int $documentId, int $expires, string $sig): bool
    {
        if (time() > $expires) {
            return false;
        }
        $expected = hash_hmac('sha256', "portal_doc:{$documentId}:{$expires}", $this->getSecret());
        return hash_equals($expected, $sig);
    }

    private function getSecret(): string
    {
        return $this->params->get('kernel.secret');
    }
}
```

- [ ] **Step 3: Create `src/Service/PortalInvoiceService.php`**

```php
<?php
namespace App\Service;

use App\Entity\Client;
use App\Misc\Enum\EbitNoteType;
use App\Repository\EbitNoteRepository;

class PortalInvoiceService
{
    public function __construct(
        private readonly EbitNoteRepository $ebitNoteRepository,
    ) {}

    public function getInvoicesForClient(Client $client): array
    {
        return $this->ebitNoteRepository->createQueryBuilder('e')
            ->innerJoin('e.shipment', 's')
            ->innerJoin('s.parties', 'p')
            ->where('p.client = :client')
            ->andWhere('e.type = :type')
            ->setParameter('client', $client)
            ->setParameter('type', EbitNoteType::InvoiceDebit)
            ->orderBy('e.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
```

- [ ] **Step 4: Create `src/Service/PortalQuoteRequestService.php`**

```php
<?php
namespace App\Service;

use App\Entity\PortalQuoteRequest;
use App\Entity\PortalUser;
use App\Repository\PortalQuoteRequestRepository;

class PortalQuoteRequestService
{
    public function __construct(
        private readonly PortalQuoteRequestRepository $repository,
    ) {}

    public function create(PortalUser $user, array $body): PortalQuoteRequest
    {
        $qr = new PortalQuoteRequest();
        $qr->setPortalUser($user);
        $qr->setTransportMode($body['transportMode'] ?? null);
        $qr->setServiceType($body['serviceType'] ?? null);
        $qr->setOrigin($body['origin'] ?? null);
        $qr->setDestination($body['destination'] ?? null);
        $qr->setCargoDescription($body['cargoDescription'] ?? null);
        $qr->setWeightKg(isset($body['weightKg']) ? (string) $body['weightKg'] : null);
        $qr->setVolumeCbm(isset($body['volumeCbm']) ? (string) $body['volumeCbm'] : null);
        $qr->setContainerType($body['containerType'] ?? null);
        $qr->setIncoterm($body['incoterm'] ?? null);
        $qr->setSpecialRequirements($body['specialRequirements'] ?? null);
        if (!empty($body['cargoReadyDate'])) {
            $qr->setCargoReadyDate(new \DateTime($body['cargoReadyDate']));
        }
        return $this->repository->save($qr);
    }
}
```

- [ ] **Step 5: Create `src/Controller/Portal/PortalShipmentController.php`**

```php
<?php
namespace App\Controller\Portal;

use App\Service\PortalShipmentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/portal/shipments')]
#[IsGranted('ROLE_PORTAL_USER')]
class PortalShipmentController extends AbstractController
{
    public function __construct(
        private readonly PortalShipmentService $service,
    ) {}

    #[Route('', methods: ['GET'])]
    public function list(#[CurrentUser] $user, Request $request): JsonResponse
    {
        /** @var \App\Entity\PortalUser $user */
        $limit  = min((int) $request->query->get('limit', 20), 100);
        $offset = max((int) $request->query->get('offset', 0), 0);
        $shipments = $this->service->getShipmentsForClient($user->getClient(), $limit, $offset);

        return $this->json(array_map(fn($s) => [
            'id'            => $s->getId(),
            'code'          => $s->getCode(),
            'transportMode' => $s->getTransportMode()?->value,
            'status'        => $s->getStatus()?->value,
            'pol'           => $s->getBooking()?->getPol()?->getCode(),
            'pod'           => $s->getBooking()?->getPod()?->getCode(),
            'etd'           => $s->getBooking()?->getEtd()?->format('Y-m-d'),
            'eta'           => $s->getBooking()?->getEta()?->format('Y-m-d'),
            'createdAt'     => $s->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ], $shipments));
    }

    #[Route('/{id}', methods: ['GET'])]
    public function detail(#[CurrentUser] $user, int $id): JsonResponse
    {
        /** @var \App\Entity\PortalUser $user */
        $shipment = $this->service->getShipmentForClient($id, $user->getClient());
        if (!$shipment) {
            throw $this->createNotFoundException();
        }

        $milestones = [];
        foreach ($shipment->getMilestones() as $m) {
            if (!$m->getMilestoneCode()->isCustomerVisible()) {
                continue;
            }
            $milestones[] = [
                'milestoneCode' => $m->getMilestoneCode()->value,
                'label'         => $m->getMilestoneCode()->customerLabel(),
                'plannedDate'   => $m->getPlannedDate()?->format(\DateTimeInterface::ATOM),
                'actualDate'    => $m->getActualDate()?->format(\DateTimeInterface::ATOM),
            ];
        }

        return $this->json([
            'id'            => $shipment->getId(),
            'code'          => $shipment->getCode(),
            'transportMode' => $shipment->getTransportMode()?->value,
            'status'        => $shipment->getStatus()?->value,
            'pol'           => $shipment->getBooking()?->getPol()?->getCode(),
            'pod'           => $shipment->getBooking()?->getPod()?->getCode(),
            'etd'           => $shipment->getBooking()?->getEtd()?->format('Y-m-d'),
            'eta'           => $shipment->getBooking()?->getEta()?->format('Y-m-d'),
            'milestones'    => $milestones,
        ]);
    }
}
```

- [ ] **Step 6: Create `src/Controller/Portal/PortalDocumentController.php`**

```php
<?php
namespace App\Controller\Portal;

use App\Repository\ShipmentDocumentRepository;
use App\Service\PortalDocumentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/portal/documents')]
class PortalDocumentController extends AbstractController
{
    public function __construct(
        private readonly PortalDocumentService      $service,
        private readonly ShipmentDocumentRepository $documentRepository,
    ) {}

    #[Route('', methods: ['GET'])]
    #[IsGranted('ROLE_PORTAL_USER')]
    public function list(#[CurrentUser] $user): JsonResponse
    {
        /** @var \App\Entity\PortalUser $user */
        $docs = $this->service->getDocumentsForClient($user->getClient());

        return $this->json(array_map(fn($d) => [
            'id'          => $d->getId(),
            'type'        => $d->getType()?->value,
            'typeLabel'   => $d->getType()?->label(),
            'shipmentId'  => $d->getShipment()?->getId(),
            'shipmentCode'=> $d->getShipment()?->getCode(),
            'issueDate'   => $d->getIssueDate()?->format('Y-m-d'),
        ], $docs));
    }

    #[Route('/{id}/download-url', methods: ['GET'])]
    #[IsGranted('ROLE_PORTAL_USER')]
    public function downloadUrl(#[CurrentUser] $user, int $id, Request $request): JsonResponse
    {
        /** @var \App\Entity\PortalUser $user */
        $doc = $this->documentRepository->find($id);
        if (!$doc || !$doc->isCustomerAccessible()) {
            throw $this->createNotFoundException();
        }

        $baseUrl = $request->getSchemeAndHttpHost();
        $url = $this->service->generateDownloadUrl($id, $baseUrl);

        return $this->json(['url' => $url, 'expiresIn' => 900]);
    }

    #[Route('/{id}/file', methods: ['GET'])]
    public function file(int $id, Request $request): Response
    {
        $expires = (int) $request->query->get('expires', 0);
        $sig     = (string) $request->query->get('sig', '');

        if (!$this->service->validateDownloadSignature($id, $expires, $sig)) {
            return new JsonResponse(['error' => 'Invalid or expired link.'], Response::HTTP_FORBIDDEN);
        }

        $doc = $this->documentRepository->find($id);
        if (!$doc || !$doc->isCustomerAccessible() || !$doc->getMedia()) {
            throw $this->createNotFoundException();
        }

        $media = $doc->getMedia();
        return $this->redirect($media->getPath());
    }
}
```

- [ ] **Step 7: Create `src/Controller/Portal/PortalInvoiceController.php`**

```php
<?php
namespace App\Controller\Portal;

use App\Service\PortalInvoiceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/portal/invoices')]
#[IsGranted('ROLE_PORTAL_USER')]
class PortalInvoiceController extends AbstractController
{
    public function __construct(
        private readonly PortalInvoiceService $service,
    ) {}

    #[Route('', methods: ['GET'])]
    public function list(#[CurrentUser] $user): JsonResponse
    {
        /** @var \App\Entity\PortalUser $user */
        $invoices = $this->service->getInvoicesForClient($user->getClient());

        return $this->json(array_map(fn($e) => [
            'id'           => $e->getId(),
            'code'         => $e->getCode(),
            'shipmentId'   => $e->getShipment()?->getId(),
            'shipmentCode' => $e->getShipment()?->getCode(),
            'amount'       => $e->getAmount()?->getAmount(),
            'currency'     => $e->getAmount()?->getCurrency()->getCode(),
            'status'       => $e->getStatus()?->value,
            'createdAt'    => $e->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ], $invoices));
    }
}
```

- [ ] **Step 8: Create `src/Controller/Portal/PortalQuoteRequestController.php`**

```php
<?php
namespace App\Controller\Portal;

use App\Repository\PortalQuoteRequestRepository;
use App\Service\PortalQuoteRequestService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/portal/quote-requests')]
#[IsGranted('ROLE_PORTAL_USER')]
class PortalQuoteRequestController extends AbstractController
{
    public function __construct(
        private readonly PortalQuoteRequestService    $service,
        private readonly PortalQuoteRequestRepository $repository,
    ) {}

    #[Route('', methods: ['GET'])]
    public function list(#[CurrentUser] $user): JsonResponse
    {
        /** @var \App\Entity\PortalUser $user */
        $requests = $this->repository->findByPortalUser($user);

        return $this->json(array_map(fn($q) => [
            'id'            => $q->getId(),
            'transportMode' => $q->getTransportMode(),
            'origin'        => $q->getOrigin(),
            'destination'   => $q->getDestination(),
            'status'        => $q->getStatus(),
            'cargoReadyDate'=> $q->getCargoReadyDate()?->format('Y-m-d'),
            'createdAt'     => $q->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ], $requests));
    }

    #[Route('', methods: ['POST'])]
    public function create(#[CurrentUser] $user, Request $request): JsonResponse
    {
        /** @var \App\Entity\PortalUser $user */
        $body = json_decode($request->getContent(), true) ?? [];
        $qr = $this->service->create($user, $body);

        return $this->json([
            'id'     => $qr->getId(),
            'status' => $qr->getStatus(),
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['GET'])]
    public function detail(#[CurrentUser] $user, int $id): JsonResponse
    {
        /** @var \App\Entity\PortalUser $user */
        $qr = $this->repository->find($id);
        if (!$qr || $qr->getPortalUser()->getId() !== $user->getId()) {
            throw $this->createNotFoundException();
        }

        return $this->json([
            'id'                  => $qr->getId(),
            'transportMode'       => $qr->getTransportMode(),
            'serviceType'         => $qr->getServiceType(),
            'origin'              => $qr->getOrigin(),
            'destination'         => $qr->getDestination(),
            'cargoDescription'    => $qr->getCargoDescription(),
            'weightKg'            => $qr->getWeightKg(),
            'volumeCbm'           => $qr->getVolumeCbm(),
            'containerType'       => $qr->getContainerType(),
            'incoterm'            => $qr->getIncoterm(),
            'cargoReadyDate'      => $qr->getCargoReadyDate()?->format('Y-m-d'),
            'specialRequirements' => $qr->getSpecialRequirements(),
            'status'              => $qr->getStatus(),
            'createdAt'           => $qr->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ]);
    }
}
```

- [ ] **Step 9: Commit**

```
git add src/Service/PortalShipmentService.php src/Service/PortalDocumentService.php
git add src/Service/PortalInvoiceService.php src/Service/PortalQuoteRequestService.php
git add src/Controller/Portal/
git commit -m "feat: add portal shipment, document, invoice, quote request API endpoints"
```
_(Run in `d:\Projects\make-cargo-client`)_

---

## Task 7: Client BO — Portal Auth Store + Layout + Login Page

_(All in `d:\Projects\make-cargo-client-bo`)_

**Files:**
- Create: `src/stores/portalAuthStore.js`
- Create: `src/services/portal/PortalAuthService.js`
- Create: `src/layouts/portal.vue`
- Create: `src/pages/portal/login.vue`

Before writing, read the existing `src/stores/authStore.js` and `src/pages/login.vue` for patterns.

- [ ] **Step 1: Create `src/stores/portalAuthStore.js`**

Follows the same pattern as `authStore.js` — stores in cookies, separate namespace.

```js
import { defineStore } from 'pinia'
import { useCookies } from '@vueuse/integrations/useCookies'

const COOKIE_TOKEN = 'portal_access_token'
const COOKIE_USER  = 'portal_user'

export const usePortalAuthStore = defineStore('portalAuth', {
  state: () => {
    const cookies = useCookies([COOKIE_TOKEN, COOKIE_USER])
    return {
      accessToken: cookies.get(COOKIE_TOKEN) ?? null,
      user: cookies.get(COOKIE_USER) ?? null,
    }
  },

  actions: {
    login(token, user) {
      const cookies = useCookies([COOKIE_TOKEN, COOKIE_USER])
      this.accessToken = token
      this.user = user
      cookies.set(COOKIE_TOKEN, token, { path: '/', maxAge: 864000 })
      cookies.set(COOKIE_USER, user, { path: '/', maxAge: 864000 })
    },

    logout() {
      const cookies = useCookies([COOKIE_TOKEN, COOKIE_USER])
      this.accessToken = null
      this.user = null
      cookies.remove(COOKIE_TOKEN, { path: '/' })
      cookies.remove(COOKIE_USER, { path: '/' })
    },

    isAuthenticated() {
      return !!this.accessToken
    },
  },
})
```

- [ ] **Step 2: Create `src/services/portal/PortalAuthService.js`**

Portal API calls use a different base header: `X-W-Auth` with the portal token. Look at how `$api` is configured globally in the BO (check `src/plugins/` for the `$api` setup) — portal requests need to attach the portal token. Use `$fetch` with manual header if `$api` only supports internal user tokens.

```js
import { usePortalAuthStore } from '@/stores/portalAuthStore'

const BASE = '/portal'

function portalFetch(path, options = {}) {
  const store = usePortalAuthStore()
  const headers = { ...options.headers }
  if (store.accessToken && store.user?.email) {
    headers['X-W-Auth'] = `Token Email="${store.user.email}", Token="${store.accessToken}"`
  }
  return $api(`${BASE}${path}`, { ...options, headers })
}

export default {
  login(email, password) {
    return $api(`${BASE}/auth`, { method: 'POST', body: { email, password } })
  },
  logout() {
    return portalFetch('/logout', { method: 'POST' })
  },
  me() {
    return portalFetch('/me')
  },
}

export { portalFetch }
```

- [ ] **Step 3: Create `src/layouts/portal.vue`**

A minimal layout — top bar with logo + portal user name + logout, no sidebar. Check if `src/layouts/` already exists (it may already have a `default.vue`). If not, create the directory.

```vue
<script setup>
import { usePortalAuthStore } from '@/stores/portalAuthStore'
import PortalAuthService from '@/services/portal/PortalAuthService'
import { useRouter } from 'vue-router'

const portalStore = usePortalAuthStore()
const router = useRouter()

async function logout() {
  try { await PortalAuthService.logout() } catch {}
  portalStore.logout()
  router.push('/portal/login')
}
</script>
<template>
  <VApp>
    <VAppBar elevation="1" color="white">
      <VAppBarTitle>
        <span class="text-primary font-weight-bold">Customer Portal</span>
      </VAppBarTitle>
      <template #append>
        <span class="text-body-2 me-4 text-medium-emphasis">{{ portalStore.user?.email }}</span>
        <VBtn variant="text" size="small" @click="logout">
          <VIcon icon="tabler-logout" class="me-1" size="18" /> {{ $gettext('Logout') }}
        </VBtn>
      </template>
    </VAppBar>
    <VMain>
      <VContainer fluid class="py-6">
        <slot />
      </VContainer>
    </VMain>
  </VApp>
</template>
```

- [ ] **Step 4: Create `src/pages/portal/login.vue`**

```vue
<script setup>
import { usePortalAuthStore } from '@/stores/portalAuthStore'
import PortalAuthService from '@/services/portal/PortalAuthService'
import { useRouter } from 'vue-router'

definePage({ meta: { layout: 'blank' } })

const router = useRouter()
const portalStore = usePortalAuthStore()

const email = ref('')
const password = ref('')
const loading = ref(false)
const error = ref('')

async function login() {
  error.value = ''
  loading.value = true
  try {
    const result = await PortalAuthService.login(email.value, password.value)
    if (result?.accessToken) {
      portalStore.login(result.accessToken, result.user)
      router.push('/portal/dashboard')
    } else {
      error.value = $gettext('Invalid credentials.')
    }
  } catch {
    error.value = $gettext('Invalid credentials.')
  }
  loading.value = false
}
</script>
<template>
  <VApp>
    <VMain class="d-flex align-center justify-center" style="min-height: 100vh; background: #f5f5f5;">
      <VCard width="400" class="pa-6">
        <VCardTitle class="text-h5 mb-4 text-center">{{ $gettext('Customer Portal') }}</VCardTitle>
        <VAlert v-if="error" type="error" class="mb-4" density="compact">{{ error }}</VAlert>
        <VTextField
          v-model="email"
          :label="$gettext('Email')"
          type="email"
          density="compact"
          class="mb-3"
          @keyup.enter="login"
        />
        <VTextField
          v-model="password"
          :label="$gettext('Password')"
          type="password"
          density="compact"
          class="mb-4"
          @keyup.enter="login"
        />
        <VBtn block color="primary" :loading="loading" @click="login">
          {{ $gettext('Sign In') }}
        </VBtn>
      </VCard>
    </VMain>
  </VApp>
</template>
```

- [ ] **Step 5: Add portal route guard**

Find where global route guards are set up in the BO — likely `src/router/index.js`, `src/plugins/router.js`, or a file referenced in `main.js`. Add a guard that redirects unauthenticated portal users to `/portal/login`:

```js
router.beforeEach((to, from, next) => {
  // existing guard logic...

  // Portal guard
  if (to.path.startsWith('/portal') && to.path !== '/portal/login') {
    const { usePortalAuthStore } = await import('@/stores/portalAuthStore')
    const portalStore = usePortalAuthStore()
    if (!portalStore.isAuthenticated()) {
      return next('/portal/login')
    }
  }

  next()
})
```

**Note:** Read the existing router file first to understand its structure before adding — slot the portal guard in alongside (not replacing) the existing internal auth guard.

- [ ] **Step 6: Commit**

```
git add src/stores/portalAuthStore.js src/services/portal/PortalAuthService.js
git add src/layouts/portal.vue src/pages/portal/login.vue
git commit -m "feat: add portal auth store, layout, login page, and route guard"
```
_(Run in `d:\Projects\make-cargo-client-bo`)_

---

## Task 8: Client BO — Portal Dashboard + Shipments

_(All in `d:\Projects\make-cargo-client-bo`)_

**Files:**
- Create: `src/services/portal/PortalShipmentService.js`
- Create: `src/pages/portal/dashboard.vue`
- Create: `src/pages/portal/shipments.vue`
- Create: `src/pages/portal/shipments/[id].vue`

- [ ] **Step 1: Create `src/services/portal/PortalShipmentService.js`**

```js
import { portalFetch } from '@/services/portal/PortalAuthService'

export default {
  list(params = '') {
    return portalFetch(`/shipments?${params}`)
  },
  get(id) {
    return portalFetch(`/shipments/${id}`)
  },
}
```

- [ ] **Step 2: Create `src/pages/portal/dashboard.vue`**

```vue
<script setup>
import { usePortalAuthStore } from '@/stores/portalAuthStore'
import PortalShipmentService from '@/services/portal/PortalShipmentService'

definePage({ meta: { layout: 'portal' } })

const portalStore = usePortalAuthStore()
const recentShipments = ref([])
const loading = ref(true)

onMounted(async () => {
  recentShipments.value = await PortalShipmentService.list('limit=5') ?? []
  loading.value = false
})
</script>
<template>
  <div>
    <h1 class="text-h5 mb-6">{{ $gettext('Welcome') }}, {{ portalStore.user?.email }}</h1>

    <VRow>
      <VCol cols="12" md="4">
        <VCard :to="'/portal/shipments'" hover>
          <VCardText class="text-center py-6">
            <VIcon icon="tabler-package-export" size="40" color="primary" class="mb-2" />
            <div class="text-h6">{{ $gettext('My Shipments') }}</div>
            <div class="text-body-2 text-medium-emphasis">{{ $gettext('Track active and historical jobs') }}</div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" md="4">
        <VCard :to="'/portal/documents'" hover>
          <VCardText class="text-center py-6">
            <VIcon icon="tabler-file-download" size="40" color="primary" class="mb-2" />
            <div class="text-h6">{{ $gettext('Documents') }}</div>
            <div class="text-body-2 text-medium-emphasis">{{ $gettext('Download BL, invoices, and more') }}</div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" md="4">
        <VCard :to="'/portal/quote-request'" hover>
          <VCardText class="text-center py-6">
            <VIcon icon="tabler-message-2-dollar" size="40" color="primary" class="mb-2" />
            <div class="text-h6">{{ $gettext('Request a Quote') }}</div>
            <div class="text-body-2 text-medium-emphasis">{{ $gettext('Submit a freight enquiry') }}</div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VCard class="mt-6" :loading="loading">
      <VCardTitle>{{ $gettext('Recent Shipments') }}</VCardTitle>
      <VTable density="compact">
        <thead>
          <tr>
            <th>{{ $gettext('Code') }}</th>
            <th>{{ $gettext('Mode') }}</th>
            <th>{{ $gettext('Origin → Destination') }}</th>
            <th>{{ $gettext('ETD') }}</th>
            <th>{{ $gettext('ETA') }}</th>
            <th>{{ $gettext('Status') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="s in recentShipments" :key="s.id" style="cursor:pointer" @click="$router.push(`/portal/shipments/${s.id}`)">
            <td>{{ s.code }}</td>
            <td>{{ s.transportMode }}</td>
            <td>{{ s.pol }} → {{ s.pod }}</td>
            <td>{{ s.etd }}</td>
            <td>{{ s.eta }}</td>
            <td><VChip size="small">{{ s.status }}</VChip></td>
          </tr>
          <tr v-if="!recentShipments.length && !loading">
            <td colspan="6" class="text-center text-medium-emphasis py-4">{{ $gettext('No shipments found') }}</td>
          </tr>
        </tbody>
      </VTable>
    </VCard>
  </div>
</template>
```

- [ ] **Step 3: Create `src/pages/portal/shipments.vue`**

```vue
<script setup>
import PortalShipmentService from '@/services/portal/PortalShipmentService'

definePage({ meta: { layout: 'portal' } })

const shipments = ref([])
const loading = ref(true)

onMounted(async () => {
  shipments.value = await PortalShipmentService.list('limit=100') ?? []
  loading.value = false
})
</script>
<template>
  <div>
    <h1 class="text-h5 mb-6">{{ $gettext('My Shipments') }}</h1>
    <VCard :loading="loading">
      <VTable density="compact">
        <thead>
          <tr>
            <th>{{ $gettext('Code') }}</th>
            <th>{{ $gettext('Mode') }}</th>
            <th>{{ $gettext('Origin') }}</th>
            <th>{{ $gettext('Destination') }}</th>
            <th>{{ $gettext('ETD') }}</th>
            <th>{{ $gettext('ETA') }}</th>
            <th>{{ $gettext('Status') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="s in shipments"
            :key="s.id"
            style="cursor:pointer"
            @click="$router.push(`/portal/shipments/${s.id}`)"
          >
            <td class="font-weight-medium">{{ s.code }}</td>
            <td>{{ s.transportMode }}</td>
            <td>{{ s.pol }}</td>
            <td>{{ s.pod }}</td>
            <td>{{ s.etd }}</td>
            <td>{{ s.eta }}</td>
            <td><VChip size="small">{{ s.status }}</VChip></td>
          </tr>
          <tr v-if="!shipments.length && !loading">
            <td colspan="7" class="text-center text-medium-emphasis py-6">{{ $gettext('No shipments found') }}</td>
          </tr>
        </tbody>
      </VTable>
    </VCard>
  </div>
</template>
```

- [ ] **Step 4: Create `src/pages/portal/shipments/[id].vue`**

```vue
<script setup>
import { useRoute } from 'vue-router'
import PortalShipmentService from '@/services/portal/PortalShipmentService'

definePage({ meta: { layout: 'portal' } })

const route = useRoute()
const shipment = ref(null)
const loading = ref(true)

onMounted(async () => {
  shipment.value = await PortalShipmentService.get(route.params.id)
  loading.value = false
})
</script>
<template>
  <div>
    <VBtn variant="text" prepend-icon="tabler-arrow-left" :to="'/portal/shipments'" class="mb-4">
      {{ $gettext('Back') }}
    </VBtn>

    <VProgressLinear v-if="loading" indeterminate class="mb-4" />

    <template v-if="shipment">
      <h1 class="text-h5 mb-2">{{ shipment.code }}</h1>
      <div class="text-body-2 text-medium-emphasis mb-6">
        {{ shipment.transportMode }} · {{ shipment.pol }} → {{ shipment.pod }}
      </div>

      <VRow>
        <VCol cols="6" md="3">
          <div class="text-caption text-medium-emphasis">{{ $gettext('ETD') }}</div>
          <div class="font-weight-medium">{{ shipment.etd ?? '—' }}</div>
        </VCol>
        <VCol cols="6" md="3">
          <div class="text-caption text-medium-emphasis">{{ $gettext('ETA') }}</div>
          <div class="font-weight-medium">{{ shipment.eta ?? '—' }}</div>
        </VCol>
        <VCol cols="6" md="3">
          <div class="text-caption text-medium-emphasis">{{ $gettext('Status') }}</div>
          <VChip size="small" class="mt-1">{{ shipment.status }}</VChip>
        </VCol>
      </VRow>

      <VCard class="mt-6">
        <VCardTitle>{{ $gettext('Milestone Timeline') }}</VCardTitle>
        <VCardText>
          <VTimeline v-if="shipment.milestones?.length" density="compact" side="end">
            <VTimelineItem
              v-for="m in shipment.milestones"
              :key="m.milestoneCode"
              :dot-color="m.actualDate ? 'success' : 'grey-lighten-2'"
              size="small"
            >
              <div class="font-weight-medium">{{ m.label }}</div>
              <div class="text-body-2 text-medium-emphasis">
                {{ m.actualDate ? new Date(m.actualDate).toLocaleString() : (m.plannedDate ? $gettext('Expected: ') + new Date(m.plannedDate).toLocaleDateString() : $gettext('Pending')) }}
              </div>
            </VTimelineItem>
          </VTimeline>
          <div v-else class="text-medium-emphasis">{{ $gettext('No tracking updates yet') }}</div>
        </VCardText>
      </VCard>
    </template>
  </div>
</template>
```

- [ ] **Step 5: Commit**

```
git add src/services/portal/PortalShipmentService.js
git add src/pages/portal/dashboard.vue src/pages/portal/shipments.vue src/pages/portal/shipments/
git commit -m "feat: add portal dashboard and shipment list/detail pages"
```
_(Run in `d:\Projects\make-cargo-client-bo`)_

---

## Task 9: Client BO — Portal Documents + Invoices

**Files:**
- Create: `src/services/portal/PortalDocumentService.js`
- Create: `src/services/portal/PortalInvoiceService.js`
- Create: `src/pages/portal/documents.vue`
- Create: `src/pages/portal/invoices.vue`

- [ ] **Step 1: Create `src/services/portal/PortalDocumentService.js`**

```js
import { portalFetch } from '@/services/portal/PortalAuthService'

export default {
  list() {
    return portalFetch('/documents')
  },
  getDownloadUrl(id) {
    return portalFetch(`/documents/${id}/download-url`)
  },
}
```

- [ ] **Step 2: Create `src/services/portal/PortalInvoiceService.js`**

```js
import { portalFetch } from '@/services/portal/PortalAuthService'

export default {
  list() {
    return portalFetch('/invoices')
  },
}
```

- [ ] **Step 3: Create `src/pages/portal/documents.vue`**

```vue
<script setup>
import PortalDocumentService from '@/services/portal/PortalDocumentService'

definePage({ meta: { layout: 'portal' } })

const documents = ref([])
const loading = ref(true)
const downloading = ref(null)

onMounted(async () => {
  documents.value = await PortalDocumentService.list() ?? []
  loading.value = false
})

async function download(doc) {
  downloading.value = doc.id
  try {
    const result = await PortalDocumentService.getDownloadUrl(doc.id)
    if (result?.url) {
      window.open(result.url, '_blank')
    }
  } finally {
    downloading.value = null
  }
}
</script>
<template>
  <div>
    <h1 class="text-h5 mb-6">{{ $gettext('Documents') }}</h1>
    <VCard :loading="loading">
      <VTable density="compact">
        <thead>
          <tr>
            <th>{{ $gettext('Type') }}</th>
            <th>{{ $gettext('Shipment') }}</th>
            <th>{{ $gettext('Issue Date') }}</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="doc in documents" :key="doc.id">
            <td>
              <VChip size="small" color="primary">{{ doc.typeLabel }}</VChip>
            </td>
            <td>
              <RouterLink :to="`/portal/shipments/${doc.shipmentId}`" class="text-decoration-none">
                {{ doc.shipmentCode }}
              </RouterLink>
            </td>
            <td>{{ doc.issueDate ?? '—' }}</td>
            <td>
              <VBtn
                size="x-small"
                variant="tonal"
                color="primary"
                :loading="downloading === doc.id"
                prepend-icon="tabler-download"
                @click="download(doc)"
              >
                {{ $gettext('Download') }}
              </VBtn>
            </td>
          </tr>
          <tr v-if="!documents.length && !loading">
            <td colspan="4" class="text-center text-medium-emphasis py-6">{{ $gettext('No documents available') }}</td>
          </tr>
        </tbody>
      </VTable>
    </VCard>
  </div>
</template>
```

- [ ] **Step 4: Create `src/pages/portal/invoices.vue`**

```vue
<script setup>
import PortalInvoiceService from '@/services/portal/PortalInvoiceService'

definePage({ meta: { layout: 'portal' } })

const invoices = ref([])
const loading = ref(true)

onMounted(async () => {
  invoices.value = await PortalInvoiceService.list() ?? []
  loading.value = false
})

function statusColor(status) {
  if (status === 'D') return 'success'
  if (status === 'S') return 'info'
  return 'warning'
}
</script>
<template>
  <div>
    <h1 class="text-h5 mb-6">{{ $gettext('Invoices') }}</h1>
    <VCard :loading="loading">
      <VTable density="compact">
        <thead>
          <tr>
            <th>{{ $gettext('Invoice No.') }}</th>
            <th>{{ $gettext('Shipment') }}</th>
            <th>{{ $gettext('Amount') }}</th>
            <th>{{ $gettext('Currency') }}</th>
            <th>{{ $gettext('Status') }}</th>
            <th>{{ $gettext('Date') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="inv in invoices" :key="inv.id">
            <td class="font-weight-medium">{{ inv.code }}</td>
            <td>
              <RouterLink :to="`/portal/shipments/${inv.shipmentId}`" class="text-decoration-none">
                {{ inv.shipmentCode }}
              </RouterLink>
            </td>
            <td>{{ Number(inv.amount).toLocaleString() }}</td>
            <td>{{ inv.currency }}</td>
            <td><VChip size="small" :color="statusColor(inv.status)">{{ inv.status }}</VChip></td>
            <td>{{ inv.createdAt ? new Date(inv.createdAt).toLocaleDateString() : '—' }}</td>
          </tr>
          <tr v-if="!invoices.length && !loading">
            <td colspan="6" class="text-center text-medium-emphasis py-6">{{ $gettext('No invoices found') }}</td>
          </tr>
        </tbody>
      </VTable>
    </VCard>
  </div>
</template>
```

- [ ] **Step 5: Commit**

```
git add src/services/portal/PortalDocumentService.js src/services/portal/PortalInvoiceService.js
git add src/pages/portal/documents.vue src/pages/portal/invoices.vue
git commit -m "feat: add portal documents and invoices pages"
```
_(Run in `d:\Projects\make-cargo-client-bo`)_

---

## Task 10: Client BO — Portal Quote Request

**Files:**
- Create: `src/services/portal/PortalQuoteRequestService.js`
- Create: `src/pages/portal/quote-request.vue`

- [ ] **Step 1: Create `src/services/portal/PortalQuoteRequestService.js`**

```js
import { portalFetch } from '@/services/portal/PortalAuthService'

export default {
  list() {
    return portalFetch('/quote-requests')
  },
  get(id) {
    return portalFetch(`/quote-requests/${id}`)
  },
  submit(payload) {
    return portalFetch('/quote-requests', { method: 'POST', body: payload, loading: true })
  },
}
```

- [ ] **Step 2: Create `src/pages/portal/quote-request.vue`**

```vue
<script setup>
import PortalQuoteRequestService from '@/services/portal/PortalQuoteRequestService'

definePage({ meta: { layout: 'portal' } })

const router = useRouter()
const submitting = ref(false)
const submitted = ref(false)
const error = ref('')

const form = ref({
  transportMode: 'SEA',
  serviceType: 'FCL',
  origin: '',
  destination: '',
  cargoDescription: '',
  weightKg: '',
  volumeCbm: '',
  containerType: '',
  incoterm: '',
  cargoReadyDate: '',
  specialRequirements: '',
})

const transportModes = ['SEA', 'AIR', 'ROAD', 'RAIL']
const serviceTypes   = ['FCL', 'LCL', 'AIR', 'EXPRESS', 'CUSTOMS']
const containerTypes = ['20GP', '40GP', '40HC', '45HC', '20RF', '40RF']
const incoterms      = ['EXW', 'FOB', 'CIF', 'CFR', 'DAP', 'DDP', 'CPT', 'FCA']

const myRequests = ref([])
onMounted(async () => {
  myRequests.value = await PortalQuoteRequestService.list() ?? []
})

async function submit() {
  if (!form.value.origin || !form.value.destination) {
    error.value = $gettext('Origin and destination are required.')
    return
  }
  error.value = ''
  submitting.value = true
  try {
    await PortalQuoteRequestService.submit({ ...form.value })
    submitted.value = true
    myRequests.value = await PortalQuoteRequestService.list() ?? []
    form.value = {
      transportMode: 'SEA', serviceType: 'FCL', origin: '', destination: '',
      cargoDescription: '', weightKg: '', volumeCbm: '', containerType: '',
      incoterm: '', cargoReadyDate: '', specialRequirements: '',
    }
  } catch {
    error.value = $gettext('Submission failed. Please try again.')
  }
  submitting.value = false
}

const statusColor = (s) => ({ RECEIVED: 'info', IN_PROGRESS: 'warning', QUOTED: 'success', CLOSED: 'default' }[s] ?? 'default')
</script>
<template>
  <div>
    <h1 class="text-h5 mb-6">{{ $gettext('Request a Freight Quote') }}</h1>

    <VAlert v-if="submitted" type="success" class="mb-4" closable @click:close="submitted = false">
      {{ $gettext('Your quote request has been submitted. Our team will be in touch shortly.') }}
    </VAlert>
    <VAlert v-if="error" type="error" class="mb-4" density="compact">{{ error }}</VAlert>

    <VCard class="mb-6">
      <VCardTitle>{{ $gettext('New Request') }}</VCardTitle>
      <VCardText>
        <VRow>
          <VCol cols="12" md="6">
            <VSelect v-model="form.transportMode" :items="transportModes" :label="$gettext('Transport Mode')" density="compact" />
          </VCol>
          <VCol cols="12" md="6">
            <VSelect v-model="form.serviceType" :items="serviceTypes" :label="$gettext('Service Type')" density="compact" />
          </VCol>
          <VCol cols="12" md="6">
            <VTextField v-model="form.origin" :label="$gettext('Origin')" density="compact" />
          </VCol>
          <VCol cols="12" md="6">
            <VTextField v-model="form.destination" :label="$gettext('Destination')" density="compact" />
          </VCol>
          <VCol cols="12">
            <VTextarea v-model="form.cargoDescription" :label="$gettext('Cargo Description')" density="compact" rows="3" />
          </VCol>
          <VCol cols="12" md="4">
            <VTextField v-model="form.weightKg" :label="$gettext('Weight (kg)')" type="number" density="compact" />
          </VCol>
          <VCol cols="12" md="4">
            <VTextField v-model="form.volumeCbm" :label="$gettext('Volume (CBM)')" type="number" density="compact" />
          </VCol>
          <VCol cols="12" md="4">
            <VSelect v-model="form.containerType" :items="['', ...containerTypes]" :label="$gettext('Container Type')" density="compact" />
          </VCol>
          <VCol cols="12" md="6">
            <VSelect v-model="form.incoterm" :items="['', ...incoterms]" :label="$gettext('Incoterm')" density="compact" />
          </VCol>
          <VCol cols="12" md="6">
            <VTextField v-model="form.cargoReadyDate" :label="$gettext('Cargo Ready Date')" type="date" density="compact" />
          </VCol>
          <VCol cols="12">
            <VTextarea v-model="form.specialRequirements" :label="$gettext('Special Requirements')" density="compact" rows="2" />
          </VCol>
          <VCol cols="12">
            <SubmitBtn :loading="submitting" @click="submit">
              {{ $gettext('Submit Request') }}
            </SubmitBtn>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <VCard v-if="myRequests.length">
      <VCardTitle>{{ $gettext('My Previous Requests') }}</VCardTitle>
      <VTable density="compact">
        <thead>
          <tr>
            <th>{{ $gettext('Date') }}</th>
            <th>{{ $gettext('Mode') }}</th>
            <th>{{ $gettext('Origin → Destination') }}</th>
            <th>{{ $gettext('Status') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="r in myRequests" :key="r.id">
            <td>{{ r.createdAt ? new Date(r.createdAt).toLocaleDateString() : '—' }}</td>
            <td>{{ r.transportMode }}</td>
            <td>{{ r.origin }} → {{ r.destination }}</td>
            <td><VChip size="small" :color="statusColor(r.status)">{{ r.status }}</VChip></td>
          </tr>
        </tbody>
      </VTable>
    </VCard>
  </div>
</template>
```

- [ ] **Step 3: Commit**

```
git add src/services/portal/PortalQuoteRequestService.js src/pages/portal/quote-request.vue
git commit -m "feat: add portal quote request form and history page"
```
_(Run in `d:\Projects\make-cargo-client-bo`)_

---

## Task 11: Documentation Guide

**File:**
- Create: `d:\Projects\make-cargo-client\docs\guides\customer-portal.md`

- [ ] **Step 1: Write guide**

Include these sections:
1. Architecture overview (portal user vs internal user, `/portal/` prefix, `Client` = organisation)
2. PortalUser entity fields and roles (VIEWER / REQUESTER / APPROVER)
3. Auth flow — `POST /portal/auth` request/response, `X-W-Auth` header for subsequent requests
4. API endpoint table (all `/portal/...` routes)
5. How shipment filtering works (via `ShipmentParty` → `Client`)
6. Milestone visibility — customer-visible codes, `customerLabel()` vs `description()`
7. Document signed URL flow — 15-min HMAC, `isCustomerAccessible` flag, how to mark a doc accessible
8. Invoice filtering — AR only (`EbitNoteType::InvoiceDebit`), never expose buy rates
9. Quote request lifecycle (RECEIVED → IN_PROGRESS → QUOTED → CLOSED)
10. Creating a portal user (no self-registration — internal staff create portal users via `PortalAuthService::createUser()`)
11. BO portal pages and their routes
12. Required env vars (none beyond existing APP_SECRET)
13. Migrations table

- [ ] **Step 2: Commit**

```
git add docs/guides/customer-portal.md docs/superpowers/plans/2026-06-24-customer-portal.md
git commit -m "docs: add customer portal guide and implementation plan"
```
_(Run in `d:\Projects\make-cargo-client`)_

---

## Self-Review

### Spec coverage
| Spec section | Task |
|---|---|
| Portal user model (roles, fields) | Task 2 |
| Separate auth from internal | Task 4, Task 5 |
| Shipment tracking (org-filtered) | Task 6 |
| Milestone timeline (customer labels) | Task 1, Task 6 |
| Document download (signed URLs) | Task 1, Task 6, Task 9 |
| Invoice view (AR only, no margins) | Task 6, Task 9 |
| Quote request form | Task 3, Task 6, Task 10 |
| Document accessibility control | Task 1 |
| Activity logging source | Task 1 |
| BO portal pages | Tasks 7–10 |
| Guide | Task 11 |

**Out of scope (excluded intentionally):**
- `portal_payment_attempt` — payment gateway integration requires external setup
- Reporting (volume/spend/on-time) — separate analytics work
- Booking requests
- 2FA/TOTP
