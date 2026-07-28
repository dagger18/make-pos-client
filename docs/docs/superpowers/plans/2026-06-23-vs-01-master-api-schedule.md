# VS-01: Master API — Vessel & Flight Schedule Infrastructure

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add vessel sailing and flight schedule entities to the master API (`d:\Projects\make-cargo`), integrate with SeaRates (ocean) and AviationStack (air) third-party APIs, and expose public search endpoints for the client API to consume.

**Architecture:** New entities (`Vessel`, `VesselSailing`, `CutoffRule`, `FlightSchedule`, `FlightItinerary`) are stored in the master API's MySQL DB as a cache. The `ScheduleService` fetches from third-party APIs on search, caches results with `fetchedAt` timestamp, and returns fresh data within 24 hours. Public endpoints are under `/api/public/` and require `X-Service-Token` header (existing pattern). Existing entities: `Port` (for port code reference).

**Tech Stack:** PHP 8.2, Symfony 7.1, Doctrine ORM 3.3, MySQL, Symfony HttpClient. Third-party: SeaRates API (ocean schedules), AviationStack API (flight schedules).

**Target repo:** `d:\Projects\make-cargo`

---

## File Structure

- Create: `src/Entity/Vessel.php`
- Create: `src/Entity/VesselSailing.php`
- Create: `src/Entity/CutoffRule.php`
- Create: `src/Entity/FlightSchedule.php`
- Create: `src/Entity/FlightItinerary.php`
- Create: `src/Repository/VesselRepository.php`
- Create: `src/Repository/VesselSailingRepository.php`
- Create: `src/Repository/FlightScheduleRepository.php`
- Create: `src/Repository/FlightItineraryRepository.php`
- Create: `src/Service/SeaRatesService.php`
- Create: `src/Service/AviationStackService.php`
- Create: `src/Service/ScheduleService.php`
- Create: `src/Controller/Http/VesselSailingController.php`
- Create: `src/Controller/Http/FlightScheduleController.php`
- Create: `migrations/Version20260623150000.php`
- Modify: `config/services.yaml` — add API key parameters
- Modify: `.env` — add SEARATES_API_KEY and AVIATIONSTACK_API_KEY

---

### Task 1: Vessel, VesselSailing, CutoffRule entities + migration

**Files:**
- Create: `src/Entity/Vessel.php`
- Create: `src/Entity/VesselSailing.php`
- Create: `src/Entity/CutoffRule.php`
- Create: `src/Repository/VesselRepository.php`
- Create: `src/Repository/VesselSailingRepository.php`
- Create: `migrations/Version20260623150000.php`

- [ ] **Step 1: Create `src/Entity/Vessel.php`**

```php
<?php
declare(strict_types=1);
namespace App\Entity;

use App\Repository\VesselRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VesselRepository::class)]
class Vessel
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20, unique: true)]
    private string $imo;

    #[ORM\Column(length: 128)]
    private string $name;

    #[ORM\Column(length: 2)]
    private string $flag = '';

    #[ORM\Column(length: 32)]
    private string $type = 'CONTAINER';

    #[ORM\Column(nullable: true)]
    private ?int $teuCapacity = null;

    #[ORM\Column(nullable: true)]
    private ?int $buildYear = null;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $callSign = null;

    public function getId(): ?int { return $this->id; }

    public function getImo(): string { return $this->imo; }
    public function setImo(string $imo): static { $this->imo = $imo; return $this; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }

    public function getFlag(): string { return $this->flag; }
    public function setFlag(string $flag): static { $this->flag = $flag; return $this; }

    public function getType(): string { return $this->type; }
    public function setType(string $type): static { $this->type = $type; return $this; }

    public function getTeuCapacity(): ?int { return $this->teuCapacity; }
    public function setTeuCapacity(?int $teuCapacity): static { $this->teuCapacity = $teuCapacity; return $this; }

    public function getBuildYear(): ?int { return $this->buildYear; }
    public function setBuildYear(?int $buildYear): static { $this->buildYear = $buildYear; return $this; }

    public function getCallSign(): ?string { return $this->callSign; }
    public function setCallSign(?string $callSign): static { $this->callSign = $callSign; return $this; }
}
```

- [ ] **Step 2: Create `src/Repository/VesselRepository.php`**

```php
<?php
declare(strict_types=1);
namespace App\Repository;

use App\Entity\Vessel;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class VesselRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Vessel::class);
    }

    public function findByImo(string $imo): ?Vessel
    {
        return $this->findOneBy(['imo' => $imo]);
    }
}
```

- [ ] **Step 3: Create `src/Entity/VesselSailing.php`**

```php
<?php
declare(strict_types=1);
namespace App\Entity;

use App\Repository\VesselSailingRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VesselSailingRepository::class)]
#[ORM\Index(columns: ['pol', 'pod', 'etd'], name: 'IDX_sailing_search')]
#[ORM\Index(columns: ['carrier'], name: 'IDX_sailing_carrier')]
class VesselSailing
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Vessel $vessel = null;

    #[ORM\Column(length: 8)]
    private string $carrier;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $service = null;

    #[ORM\Column(length: 10)]
    private string $pol;

    #[ORM\Column(length: 10)]
    private string $pod;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $voyageNo = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $etd;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $eta;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $atd = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $ata = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $cyCutOff = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $siCutOff = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $vgmCutOff = null;

    #[ORM\Column(length: 20)]
    private string $status = 'SCHEDULED';

    #[ORM\Column(length: 20)]
    private string $source = 'SEARATES';

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $fetchedAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function getId(): ?int { return $this->id; }

    public function getVessel(): ?Vessel { return $this->vessel; }
    public function setVessel(?Vessel $vessel): static { $this->vessel = $vessel; return $this; }

    public function getCarrier(): string { return $this->carrier; }
    public function setCarrier(string $carrier): static { $this->carrier = $carrier; return $this; }

    public function getService(): ?string { return $this->service; }
    public function setService(?string $service): static { $this->service = $service; return $this; }

    public function getPol(): string { return $this->pol; }
    public function setPol(string $pol): static { $this->pol = $pol; return $this; }

    public function getPod(): string { return $this->pod; }
    public function setPod(string $pod): static { $this->pod = $pod; return $this; }

    public function getVoyageNo(): ?string { return $this->voyageNo; }
    public function setVoyageNo(?string $voyageNo): static { $this->voyageNo = $voyageNo; return $this; }

    public function getEtd(): \DateTimeImmutable { return $this->etd; }
    public function setEtd(\DateTimeImmutable $etd): static { $this->etd = $etd; return $this; }

    public function getEta(): \DateTimeImmutable { return $this->eta; }
    public function setEta(\DateTimeImmutable $eta): static { $this->eta = $eta; return $this; }

    public function getAtd(): ?\DateTimeImmutable { return $this->atd; }
    public function setAtd(?\DateTimeImmutable $atd): static { $this->atd = $atd; return $this; }

    public function getAta(): ?\DateTimeImmutable { return $this->ata; }
    public function setAta(?\DateTimeImmutable $ata): static { $this->ata = $ata; return $this; }

    public function getCyCutOff(): ?\DateTimeImmutable { return $this->cyCutOff; }
    public function setCyCutOff(?\DateTimeImmutable $cyCutOff): static { $this->cyCutOff = $cyCutOff; return $this; }

    public function getSiCutOff(): ?\DateTimeImmutable { return $this->siCutOff; }
    public function setSiCutOff(?\DateTimeImmutable $siCutOff): static { $this->siCutOff = $siCutOff; return $this; }

    public function getVgmCutOff(): ?\DateTimeImmutable { return $this->vgmCutOff; }
    public function setVgmCutOff(?\DateTimeImmutable $vgmCutOff): static { $this->vgmCutOff = $vgmCutOff; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }

    public function getSource(): string { return $this->source; }
    public function setSource(string $source): static { $this->source = $source; return $this; }

    public function getFetchedAt(): \DateTimeImmutable { return $this->fetchedAt; }
    public function setFetchedAt(\DateTimeImmutable $fetchedAt): static { $this->fetchedAt = $fetchedAt; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): static { $this->createdAt = $createdAt; return $this; }
}
```

- [ ] **Step 4: Create `src/Repository/VesselSailingRepository.php`**

```php
<?php
declare(strict_types=1);
namespace App\Repository;

use App\Entity\VesselSailing;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class VesselSailingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VesselSailing::class);
    }

    public function findBySearch(string $pol, string $pod, string $etdFrom, string $etdTo): array
    {
        return $this->createQueryBuilder('vs')
            ->where('vs.pol = :pol')
            ->andWhere('vs.pod = :pod')
            ->andWhere('vs.etd >= :etdFrom')
            ->andWhere('vs.etd <= :etdTo')
            ->setParameter('pol', $pol)
            ->setParameter('pod', $pod)
            ->setParameter('etdFrom', new \DateTimeImmutable($etdFrom . ' 00:00:00'))
            ->setParameter('etdTo', new \DateTimeImmutable($etdTo . ' 23:59:59'))
            ->orderBy('vs.etd', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }

    public function findFreshBySearch(string $pol, string $pod, string $etdFrom, string $etdTo): array
    {
        $staleThreshold = new \DateTimeImmutable('-24 hours');
        return $this->createQueryBuilder('vs')
            ->where('vs.pol = :pol')
            ->andWhere('vs.pod = :pod')
            ->andWhere('vs.etd >= :etdFrom')
            ->andWhere('vs.etd <= :etdTo')
            ->andWhere('vs.fetchedAt >= :staleThreshold')
            ->setParameter('pol', $pol)
            ->setParameter('pod', $pod)
            ->setParameter('etdFrom', new \DateTimeImmutable($etdFrom . ' 00:00:00'))
            ->setParameter('etdTo', new \DateTimeImmutable($etdTo . ' 23:59:59'))
            ->setParameter('staleThreshold', $staleThreshold)
            ->orderBy('vs.etd', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }
}
```

- [ ] **Step 5: Create `src/Entity/CutoffRule.php`**

```php
<?php
declare(strict_types=1);
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class CutoffRule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 8, nullable: true)]
    private ?string $carrier = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $pol = null;

    #[ORM\Column]
    private int $cyCutOffHours = 72;

    #[ORM\Column]
    private int $siCutOffHours = 120;

    #[ORM\Column]
    private int $vgmCutOffHours = 96;

    public function getId(): ?int { return $this->id; }

    public function getCarrier(): ?string { return $this->carrier; }
    public function setCarrier(?string $carrier): static { $this->carrier = $carrier; return $this; }

    public function getPol(): ?string { return $this->pol; }
    public function setPol(?string $pol): static { $this->pol = $pol; return $this; }

    public function getCyCutOffHours(): int { return $this->cyCutOffHours; }
    public function setCyCutOffHours(int $h): static { $this->cyCutOffHours = $h; return $this; }

    public function getSiCutOffHours(): int { return $this->siCutOffHours; }
    public function setSiCutOffHours(int $h): static { $this->siCutOffHours = $h; return $this; }

    public function getVgmCutOffHours(): int { return $this->vgmCutOffHours; }
    public function setVgmCutOffHours(int $h): static { $this->vgmCutOffHours = $h; return $this; }
}
```

- [ ] **Step 6: Create migration `migrations/Version20260623150000.php`**

```php
<?php
declare(strict_types=1);
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260623150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add vessel, vessel_sailing, cutoff_rule tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE vessel (
            id INT NOT NULL AUTO_INCREMENT,
            imo VARCHAR(20) NOT NULL,
            name VARCHAR(128) NOT NULL,
            flag VARCHAR(2) NOT NULL DEFAULT '',
            type VARCHAR(32) NOT NULL DEFAULT 'CONTAINER',
            teu_capacity INT DEFAULT NULL,
            build_year INT DEFAULT NULL,
            call_sign VARCHAR(16) DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE INDEX UNIQ_vessel_imo (imo)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        $this->addSql("CREATE TABLE vessel_sailing (
            id INT NOT NULL AUTO_INCREMENT,
            vessel_id INT DEFAULT NULL,
            carrier VARCHAR(8) NOT NULL,
            service VARCHAR(64) DEFAULT NULL,
            pol VARCHAR(10) NOT NULL,
            pod VARCHAR(10) NOT NULL,
            voyage_no VARCHAR(32) DEFAULT NULL,
            etd DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
            eta DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
            atd DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
            ata DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
            cy_cut_off DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
            si_cut_off DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
            vgm_cut_off DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
            status VARCHAR(20) NOT NULL DEFAULT 'SCHEDULED',
            source VARCHAR(20) NOT NULL DEFAULT 'SEARATES',
            fetched_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
            created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
            PRIMARY KEY (id),
            INDEX IDX_sailing_vessel (vessel_id),
            INDEX IDX_sailing_search (pol, pod, etd),
            INDEX IDX_sailing_carrier (carrier),
            CONSTRAINT FK_sailing_vessel FOREIGN KEY (vessel_id) REFERENCES vessel (id) ON DELETE SET NULL
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        $this->addSql("CREATE TABLE cutoff_rule (
            id INT NOT NULL AUTO_INCREMENT,
            carrier VARCHAR(8) DEFAULT NULL,
            pol VARCHAR(10) DEFAULT NULL,
            cy_cut_off_hours INT NOT NULL DEFAULT 72,
            si_cut_off_hours INT NOT NULL DEFAULT 120,
            vgm_cut_off_hours INT NOT NULL DEFAULT 96,
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        $this->addSql("INSERT INTO cutoff_rule (cy_cut_off_hours, si_cut_off_hours, vgm_cut_off_hours) VALUES (72, 120, 96)");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE vessel_sailing DROP FOREIGN KEY FK_sailing_vessel');
        $this->addSql('DROP TABLE vessel_sailing');
        $this->addSql('DROP TABLE vessel');
        $this->addSql('DROP TABLE cutoff_rule');
    }
}
```

- [ ] **Step 7: Commit**

```bash
git add src/Entity/Vessel.php src/Entity/VesselSailing.php src/Entity/CutoffRule.php src/Repository/VesselRepository.php src/Repository/VesselSailingRepository.php migrations/Version20260623150000.php
git commit -m "feat(vs-01): add Vessel, VesselSailing, CutoffRule entities and migration"
```

---

### Task 2: FlightSchedule + FlightItinerary entities + migration

**Files:**
- Create: `src/Entity/FlightSchedule.php`
- Create: `src/Entity/FlightItinerary.php`
- Create: `src/Repository/FlightScheduleRepository.php`
- Create: `src/Repository/FlightItineraryRepository.php`
- Create: `migrations/Version20260623160000.php`

- [ ] **Step 1: Create `src/Entity/FlightSchedule.php`**

```php
<?php
declare(strict_types=1);
namespace App\Entity;

use App\Repository\FlightScheduleRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FlightScheduleRepository::class)]
#[ORM\Index(columns: ['origin_iata', 'destination_iata', 'std'], name: 'IDX_flight_search')]
class FlightSchedule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 16)]
    private string $flightNo;

    #[ORM\Column(length: 4)]
    private string $carrierIata;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $carrierName = null;

    #[ORM\Column(length: 4)]
    private string $originIata;

    #[ORM\Column(length: 4)]
    private string $destinationIata;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $std;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $sta;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $cargoCutOff = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $docCutOff = null;

    #[ORM\Column(length: 20)]
    private string $status = 'SCHEDULED';

    #[ORM\Column(length: 20)]
    private string $source = 'AVIATIONSTACK';

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $fetchedAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function getId(): ?int { return $this->id; }

    public function getFlightNo(): string { return $this->flightNo; }
    public function setFlightNo(string $flightNo): static { $this->flightNo = $flightNo; return $this; }

    public function getCarrierIata(): string { return $this->carrierIata; }
    public function setCarrierIata(string $carrierIata): static { $this->carrierIata = $carrierIata; return $this; }

    public function getCarrierName(): ?string { return $this->carrierName; }
    public function setCarrierName(?string $carrierName): static { $this->carrierName = $carrierName; return $this; }

    public function getOriginIata(): string { return $this->originIata; }
    public function setOriginIata(string $originIata): static { $this->originIata = $originIata; return $this; }

    public function getDestinationIata(): string { return $this->destinationIata; }
    public function setDestinationIata(string $destinationIata): static { $this->destinationIata = $destinationIata; return $this; }

    public function getStd(): \DateTimeImmutable { return $this->std; }
    public function setStd(\DateTimeImmutable $std): static { $this->std = $std; return $this; }

    public function getSta(): \DateTimeImmutable { return $this->sta; }
    public function setSta(\DateTimeImmutable $sta): static { $this->sta = $sta; return $this; }

    public function getCargoCutOff(): ?\DateTimeImmutable { return $this->cargoCutOff; }
    public function setCargoCutOff(?\DateTimeImmutable $cargoCutOff): static { $this->cargoCutOff = $cargoCutOff; return $this; }

    public function getDocCutOff(): ?\DateTimeImmutable { return $this->docCutOff; }
    public function setDocCutOff(?\DateTimeImmutable $docCutOff): static { $this->docCutOff = $docCutOff; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }

    public function getSource(): string { return $this->source; }
    public function setSource(string $source): static { $this->source = $source; return $this; }

    public function getFetchedAt(): \DateTimeImmutable { return $this->fetchedAt; }
    public function setFetchedAt(\DateTimeImmutable $fetchedAt): static { $this->fetchedAt = $fetchedAt; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): static { $this->createdAt = $createdAt; return $this; }
}
```

- [ ] **Step 2: Create `src/Repository/FlightScheduleRepository.php`**

```php
<?php
declare(strict_types=1);
namespace App\Repository;

use App\Entity\FlightSchedule;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class FlightScheduleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FlightSchedule::class);
    }

    public function findFreshBySearch(string $origin, string $destination, string $date): array
    {
        $staleThreshold = new \DateTimeImmutable('-24 hours');
        $dayStart = new \DateTimeImmutable($date . ' 00:00:00');
        $dayEnd = new \DateTimeImmutable($date . ' 23:59:59');
        return $this->createQueryBuilder('fs')
            ->where('fs.originIata = :origin')
            ->andWhere('fs.destinationIata = :destination')
            ->andWhere('fs.std >= :dayStart')
            ->andWhere('fs.std <= :dayEnd')
            ->andWhere('fs.fetchedAt >= :staleThreshold')
            ->setParameter('origin', $origin)
            ->setParameter('destination', $destination)
            ->setParameter('dayStart', $dayStart)
            ->setParameter('dayEnd', $dayEnd)
            ->setParameter('staleThreshold', $staleThreshold)
            ->orderBy('fs.std', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }
}
```

- [ ] **Step 3: Create `src/Entity/FlightItinerary.php`**

```php
<?php
declare(strict_types=1);
namespace App\Entity;

use App\Repository\FlightItineraryRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FlightItineraryRepository::class)]
#[ORM\Index(columns: ['origin_iata', 'destination_iata', 'departure_date'], name: 'IDX_itinerary_search')]
class FlightItinerary
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 4)]
    private string $originIata;

    #[ORM\Column(length: 4)]
    private string $destinationIata;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $departureDate;

    #[ORM\Column(type: 'json')]
    private array $legs = [];

    #[ORM\Column(length: 20)]
    private string $source = 'AVIATIONSTACK';

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $fetchedAt;

    public function getId(): ?int { return $this->id; }

    public function getOriginIata(): string { return $this->originIata; }
    public function setOriginIata(string $originIata): static { $this->originIata = $originIata; return $this; }

    public function getDestinationIata(): string { return $this->destinationIata; }
    public function setDestinationIata(string $destinationIata): static { $this->destinationIata = $destinationIata; return $this; }

    public function getDepartureDate(): \DateTimeImmutable { return $this->departureDate; }
    public function setDepartureDate(\DateTimeImmutable $departureDate): static { $this->departureDate = $departureDate; return $this; }

    public function getLegs(): array { return $this->legs; }
    public function setLegs(array $legs): static { $this->legs = $legs; return $this; }

    public function getSource(): string { return $this->source; }
    public function setSource(string $source): static { $this->source = $source; return $this; }

    public function getFetchedAt(): \DateTimeImmutable { return $this->fetchedAt; }
    public function setFetchedAt(\DateTimeImmutable $fetchedAt): static { $this->fetchedAt = $fetchedAt; return $this; }
}
```

- [ ] **Step 4: Create `src/Repository/FlightItineraryRepository.php`**

```php
<?php
declare(strict_types=1);
namespace App\Repository;

use App\Entity\FlightItinerary;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class FlightItineraryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FlightItinerary::class);
    }
}
```

- [ ] **Step 5: Create migration `migrations/Version20260623160000.php`**

```php
<?php
declare(strict_types=1);
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260623160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add flight_schedule and flight_itinerary tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE flight_schedule (
            id INT NOT NULL AUTO_INCREMENT,
            flight_no VARCHAR(16) NOT NULL,
            carrier_iata VARCHAR(4) NOT NULL,
            carrier_name VARCHAR(128) DEFAULT NULL,
            origin_iata VARCHAR(4) NOT NULL,
            destination_iata VARCHAR(4) NOT NULL,
            std DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
            sta DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
            cargo_cut_off DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
            doc_cut_off DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
            status VARCHAR(20) NOT NULL DEFAULT 'SCHEDULED',
            source VARCHAR(20) NOT NULL DEFAULT 'AVIATIONSTACK',
            fetched_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
            created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
            PRIMARY KEY (id),
            INDEX IDX_flight_search (origin_iata, destination_iata, std)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        $this->addSql("CREATE TABLE flight_itinerary (
            id INT NOT NULL AUTO_INCREMENT,
            origin_iata VARCHAR(4) NOT NULL,
            destination_iata VARCHAR(4) NOT NULL,
            departure_date DATE NOT NULL COMMENT '(DC2Type:date_immutable)',
            legs JSON NOT NULL,
            source VARCHAR(20) NOT NULL DEFAULT 'AVIATIONSTACK',
            fetched_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
            PRIMARY KEY (id),
            INDEX IDX_itinerary_search (origin_iata, destination_iata, departure_date)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE flight_schedule');
        $this->addSql('DROP TABLE flight_itinerary');
    }
}
```

- [ ] **Step 6: Commit**

```bash
git add src/Entity/FlightSchedule.php src/Entity/FlightItinerary.php src/Repository/FlightScheduleRepository.php src/Repository/FlightItineraryRepository.php migrations/Version20260623160000.php
git commit -m "feat(vs-01): add FlightSchedule and FlightItinerary entities and migration"
```

---

### Task 3: Third-party integration services + config

**Files:**
- Create: `src/Service/SeaRatesService.php`
- Create: `src/Service/AviationStackService.php`
- Modify: `config/services.yaml`
- Modify: `.env`

- [ ] **Step 1: Add API key parameters to `config/services.yaml`**

Open `config/services.yaml`. Find the `parameters:` section and add:

```yaml
    searates_api_key: '%env(SEARATES_API_KEY)%'
    aviationstack_api_key: '%env(AVIATIONSTACK_API_KEY)%'
```

- [ ] **Step 2: Add API key env vars to `.env`**

Open `.env`. Add at the end:

```dotenv
SEARATES_API_KEY=
AVIATIONSTACK_API_KEY=
```

- [ ] **Step 3: Create `src/Service/SeaRatesService.php`**

SeaRates API docs: https://api.searates.com/schedule — Bearer token auth, params: stype, pol, pod, date, limit.

```php
<?php
declare(strict_types=1);
namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SeaRatesService
{
    private const BASE_URL = 'https://api.searates.com';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ParameterBagInterface $params,
    ) {}

    /**
     * Search point-to-point ocean sailing schedules.
     * @return array<int, array{carrier: string, vessel: string, voyageNo: string, service: string, etd: string, eta: string, transitDays: int, imo: string}>
     */
    public function searchSchedules(string $pol, string $pod, string $date, int $limit = 30): array
    {
        $apiKey = $this->params->get('searates_api_key');
        if (!$apiKey) {
            return $this->getStubSchedules($pol, $pod, $date);
        }

        try {
            $response = $this->httpClient->request('GET', self::BASE_URL . '/schedule', [
                'headers' => ['Authorization' => 'Bearer ' . $apiKey],
                'query' => [
                    'stype' => 'port',
                    'pol' => $pol,
                    'pod' => $pod,
                    'date' => $date,
                    'limit' => $limit,
                ],
                'timeout' => 15,
            ]);
            $data = $response->toArray();
            return $this->normalizeSeaRatesResponse($data);
        } catch (\Throwable) {
            return [];
        }
    }

    private function normalizeSeaRatesResponse(array $data): array
    {
        $schedules = $data['schedules'] ?? $data['data'] ?? [];
        $results = [];
        foreach ($schedules as $item) {
            $results[] = [
                'carrier' => $item['carrier']['scac'] ?? $item['carrier'] ?? '',
                'carrierName' => $item['carrier']['name'] ?? '',
                'vessel' => $item['vessel']['name'] ?? $item['vessel'] ?? '',
                'imo' => $item['vessel']['imo'] ?? '',
                'voyageNo' => $item['voyage'] ?? $item['voyageNo'] ?? '',
                'service' => $item['service'] ?? '',
                'etd' => $item['etd'] ?? '',
                'eta' => $item['eta'] ?? '',
                'transitDays' => (int) ($item['transit_time'] ?? 0),
            ];
        }
        return $results;
    }

    /** Returns demo data when no API key is configured (for development/testing). */
    private function getStubSchedules(string $pol, string $pod, string $date): array
    {
        $etd = new \DateTimeImmutable($date);
        return [
            [
                'carrier' => 'MSCU',
                'carrierName' => 'MSC',
                'vessel' => 'MSC LORETO',
                'imo' => '9780351',
                'voyageNo' => 'ST526R',
                'service' => 'AEX',
                'etd' => $etd->format('Y-m-d\TH:i:s'),
                'eta' => $etd->modify('+20 days')->format('Y-m-d\TH:i:s'),
                'transitDays' => 20,
            ],
            [
                'carrier' => 'MAEU',
                'carrierName' => 'Maersk',
                'vessel' => 'MAERSK EINDHOVEN',
                'imo' => '9780363',
                'voyageNo' => 'MW124R',
                'service' => 'AE-1/Shogun',
                'etd' => $etd->modify('+3 days')->format('Y-m-d\TH:i:s'),
                'eta' => $etd->modify('+25 days')->format('Y-m-d\TH:i:s'),
                'transitDays' => 22,
            ],
        ];
    }
}
```

- [ ] **Step 4: Create `src/Service/AviationStackService.php`**

AviationStack API docs: https://aviationstack.com/documentation — access_key param, GET /v1/flights.

```php
<?php
declare(strict_types=1);
namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AviationStackService
{
    private const BASE_URL = 'http://api.aviationstack.com/v1';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ParameterBagInterface $params,
    ) {}

    /**
     * @return array<int, array{flightNo: string, carrierIata: string, carrierName: string, originIata: string, destinationIata: string, std: string, sta: string, status: string}>
     */
    public function searchFlights(string $depIata, string $arrIata, string $date, int $limit = 30): array
    {
        $apiKey = $this->params->get('aviationstack_api_key');
        if (!$apiKey) {
            return $this->getStubFlights($depIata, $arrIata, $date);
        }

        try {
            $response = $this->httpClient->request('GET', self::BASE_URL . '/flights', [
                'query' => [
                    'access_key' => $apiKey,
                    'dep_iata' => $depIata,
                    'arr_iata' => $arrIata,
                    'flight_date' => $date,
                    'limit' => $limit,
                ],
                'timeout' => 15,
            ]);
            $data = $response->toArray();
            return $this->normalizeAviationStackResponse($data);
        } catch (\Throwable) {
            return [];
        }
    }

    private function normalizeAviationStackResponse(array $data): array
    {
        $flights = $data['data'] ?? [];
        $results = [];
        foreach ($flights as $item) {
            $results[] = [
                'flightNo' => $item['flight']['iata'] ?? $item['flight']['number'] ?? '',
                'carrierIata' => $item['airline']['iata'] ?? '',
                'carrierName' => $item['airline']['name'] ?? '',
                'originIata' => $item['departure']['iata'] ?? '',
                'destinationIata' => $item['arrival']['iata'] ?? '',
                'std' => $item['departure']['scheduled'] ?? '',
                'sta' => $item['arrival']['scheduled'] ?? '',
                'status' => $item['flight_status'] ?? 'scheduled',
            ];
        }
        return $results;
    }

    private function getStubFlights(string $depIata, string $arrIata, string $date): array
    {
        $std = new \DateTimeImmutable($date . ' 10:00:00');
        return [
            [
                'flightNo' => 'SQ321',
                'carrierIata' => 'SQ',
                'carrierName' => 'Singapore Airlines',
                'originIata' => $depIata,
                'destinationIata' => $arrIata,
                'std' => $std->format('Y-m-d\TH:i:sP'),
                'sta' => $std->modify('+12 hours')->format('Y-m-d\TH:i:sP'),
                'status' => 'scheduled',
            ],
            [
                'flightNo' => 'EK408',
                'carrierIata' => 'EK',
                'carrierName' => 'Emirates',
                'originIata' => $depIata,
                'destinationIata' => $arrIata,
                'std' => $std->modify('+4 hours')->format('Y-m-d\TH:i:sP'),
                'sta' => $std->modify('+18 hours')->format('Y-m-d\TH:i:sP'),
                'status' => 'scheduled',
            ],
        ];
    }
}
```

- [ ] **Step 5: Commit**

```bash
git add src/Service/SeaRatesService.php src/Service/AviationStackService.php config/services.yaml .env
git commit -m "feat(vs-01): add SeaRates and AviationStack integration services"
```

---

### Task 4: ScheduleService — orchestrates search, cache, and cutoff calculation

**Files:**
- Create: `src/Service/ScheduleService.php`

- [ ] **Step 1: Create `src/Service/ScheduleService.php`**

```php
<?php
declare(strict_types=1);
namespace App\Service;

use App\Entity\CutoffRule;
use App\Entity\FlightSchedule;
use App\Entity\Vessel;
use App\Entity\VesselSailing;
use App\Repository\FlightScheduleRepository;
use App\Repository\VesselRepository;
use App\Repository\VesselSailingRepository;
use Doctrine\ORM\EntityManagerInterface;

class ScheduleService
{
    public function __construct(
        private readonly SeaRatesService $seaRates,
        private readonly AviationStackService $aviationStack,
        private readonly VesselSailingRepository $sailingRepo,
        private readonly VesselRepository $vesselRepo,
        private readonly FlightScheduleRepository $flightRepo,
        private readonly EntityManagerInterface $em,
    ) {}

    public function searchVesselSailings(string $pol, string $pod, string $etdFrom, string $etdTo): array
    {
        $cached = $this->sailingRepo->findFreshBySearch($pol, $pod, $etdFrom, $etdTo);
        if (!empty($cached)) {
            return $cached;
        }

        $raw = $this->seaRates->searchSchedules($pol, $pod, $etdFrom);
        $this->persistSailings($raw, $pol, $pod);
        $this->em->flush();

        return $this->sailingRepo->findBySearch($pol, $pod, $etdFrom, $etdTo);
    }

    public function searchFlightSchedules(string $origin, string $destination, string $date): array
    {
        $cached = $this->flightRepo->findFreshBySearch($origin, $destination, $date);
        if (!empty($cached)) {
            return $cached;
        }

        $raw = $this->aviationStack->searchFlights($origin, $destination, $date);
        $this->persistFlights($raw);
        $this->em->flush();

        return $this->flightRepo->findFreshBySearch($origin, $destination, $date);
    }

    private function persistSailings(array $items, string $pol, string $pod): void
    {
        $now = new \DateTimeImmutable();
        $cutoffRule = $this->em->getRepository(CutoffRule::class)->findOneBy([]) ?? $this->defaultCutoffRule();

        foreach ($items as $item) {
            if (empty($item['etd'])) {
                continue;
            }
            try {
                $etd = new \DateTimeImmutable($item['etd']);
                $eta = new \DateTimeImmutable($item['eta'] ?? $item['etd']);
            } catch (\Throwable) {
                continue;
            }

            $sailing = new VesselSailing();
            $sailing->setCarrier($item['carrier'] ?? '');
            $sailing->setService($item['service'] ?? null);
            $sailing->setPol($pol);
            $sailing->setPod($pod);
            $sailing->setVoyageNo($item['voyageNo'] ?? null);
            $sailing->setEtd($etd);
            $sailing->setEta($eta);
            $sailing->setStatus('SCHEDULED');
            $sailing->setSource('SEARATES');
            $sailing->setFetchedAt($now);
            $sailing->setCreatedAt($now);
            $sailing->setCyCutOff($etd->modify('-' . $cutoffRule->getCyCutOffHours() . ' hours'));
            $sailing->setSiCutOff($etd->modify('-' . $cutoffRule->getSiCutOffHours() . ' hours'));
            $sailing->setVgmCutOff($etd->modify('-' . $cutoffRule->getVgmCutOffHours() . ' hours'));

            if (!empty($item['imo'])) {
                $vessel = $this->vesselRepo->findByImo($item['imo']);
                if (!$vessel) {
                    $vessel = new Vessel();
                    $vessel->setImo($item['imo']);
                    $vessel->setName($item['vessel'] ?? 'Unknown');
                    $this->em->persist($vessel);
                }
                $sailing->setVessel($vessel);
            }

            $this->em->persist($sailing);
        }
    }

    private function persistFlights(array $items): void
    {
        $now = new \DateTimeImmutable();
        foreach ($items as $item) {
            if (empty($item['std'])) {
                continue;
            }
            try {
                $std = new \DateTimeImmutable($item['std']);
                $sta = new \DateTimeImmutable($item['sta'] ?? $item['std']);
            } catch (\Throwable) {
                continue;
            }

            $flight = new FlightSchedule();
            $flight->setFlightNo($item['flightNo'] ?? '');
            $flight->setCarrierIata($item['carrierIata'] ?? '');
            $flight->setCarrierName($item['carrierName'] ?? null);
            $flight->setOriginIata($item['originIata'] ?? '');
            $flight->setDestinationIata($item['destinationIata'] ?? '');
            $flight->setStd($std);
            $flight->setSta($sta);
            $flight->setCargoCutOff($std->modify('-4 hours'));
            $flight->setDocCutOff($std->modify('-6 hours'));
            $flight->setStatus($item['status'] ?? 'SCHEDULED');
            $flight->setSource('AVIATIONSTACK');
            $flight->setFetchedAt($now);
            $flight->setCreatedAt($now);

            $this->em->persist($flight);
        }
    }

    private function defaultCutoffRule(): CutoffRule
    {
        $rule = new CutoffRule();
        $rule->setCyCutOffHours(72);
        $rule->setSiCutOffHours(120);
        $rule->setVgmCutOffHours(96);
        return $rule;
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add src/Service/ScheduleService.php
git commit -m "feat(vs-01): add ScheduleService with cache-first search and cutoff calculation"
```

---

### Task 5: Public search endpoints (VesselSailingController + FlightScheduleController)

**Files:**
- Create: `src/Controller/Http/VesselSailingController.php`
- Create: `src/Controller/Http/FlightScheduleController.php`

- [ ] **Step 1: Determine how existing public controllers look**

Read `src/Controller/Http/IndexController.php` or any file in `src/Controller/Http/` that handles public endpoints to understand the base class and route prefix pattern used. Also check if `/api/public/` routes are defined in any route file.

- [ ] **Step 2: Create `src/Controller/Http/VesselSailingController.php`**

Follow the same pattern as existing public controllers (e.g., how Port and Currency search are exposed). The endpoint must be under `/api/public/vessel-sailing/search` (matching the pattern that `MasterSyncService` in the client API will call).

```php
<?php
declare(strict_types=1);
namespace App\Controller\Http;

use App\Service\ScheduleService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/public/vessel-sailing')]
class VesselSailingController extends AbstractController
{
    public function __construct(private readonly ScheduleService $scheduleService) {}

    #[Route('/search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $pol = trim($request->query->getString('pol', ''));
        $pod = trim($request->query->getString('pod', ''));
        $etdFrom = $request->query->getString('etd_from', date('Y-m-d'));
        $etdTo = $request->query->getString('etd_to', date('Y-m-d', strtotime('+60 days')));

        if (!$pol || !$pod) {
            return $this->json(['list' => []]);
        }

        $results = $this->scheduleService->searchVesselSailings($pol, $pod, $etdFrom, $etdTo);
        return $this->json(['list' => $results]);
    }
}
```

- [ ] **Step 3: Create `src/Controller/Http/FlightScheduleController.php`**

```php
<?php
declare(strict_types=1);
namespace App\Controller\Http;

use App\Service\ScheduleService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/public/flight-schedule')]
class FlightScheduleController extends AbstractController
{
    public function __construct(private readonly ScheduleService $scheduleService) {}

    #[Route('/search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $origin = trim(strtoupper($request->query->getString('origin', '')));
        $destination = trim(strtoupper($request->query->getString('destination', '')));
        $date = $request->query->getString('date', date('Y-m-d'));

        if (!$origin || !$destination) {
            return $this->json(['list' => []]);
        }

        $results = $this->scheduleService->searchFlightSchedules($origin, $destination, $date);
        return $this->json(['list' => $results]);
    }
}
```

- [ ] **Step 4: Verify routes are registered**

The existing public routes in the master API likely use attribute routing that is auto-discovered. Check `config/routes.yaml` or `config/routes/attributes.yaml` to confirm that `src/Controller/Http/` is included in route scanning. If there is a specific route config file for HTTP controllers, make sure the new controllers fall under it.

- [ ] **Step 5: Commit**

```bash
git add src/Controller/Http/VesselSailingController.php src/Controller/Http/FlightScheduleController.php
git commit -m "feat(vs-01): add public vessel-sailing and flight-schedule search endpoints"
```
