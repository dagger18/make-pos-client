# AB-05: Provider Profiles (Carrier + Agent) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add `CarrierProfile` and `AgentProfile` as optional one-to-one extensions to `Provider`, storing carrier-specific fields (SCAC, IATA, booking email, SI email) and agent-specific fields (network, coverage countries, commission rate, performance score). Each profile is shown as a conditional tab on the Provider detail view when the provider type matches.

**Architecture:** Two new entities (`CarrierProfile`, `AgentProfile`) each with `provider_id` as primary key (true one-to-one, PK = FK). `Provider` gets two nullable OneToOne relations. A new `CarrierType` enum is added. The BO shows a "Carrier Profile" tab when `provider.type` is `CA` (Carrier) or `AL` (Airline), and an "Agent Profile" tab when `provider.type` is `AG` (Agent).

**Tech Stack:** PHP 8.2, Symfony 6, Doctrine ORM, MySQL + SQLite migrations, Vue 3 + Vuetify (BO)

---

## File Structure

**API repo (`d:\Projects\make-cargo-client`):**
- Create: `src/Misc/Enum/CarrierType.php`
- Create: `src/Entity/CarrierProfile.php`
- Create: `src/Entity/AgentProfile.php`
- Create: `src/Repository/CarrierProfileRepository.php`
- Create: `src/Repository/AgentProfileRepository.php`
- Modify: `src/Entity/Provider.php` — add nullable OneToOne relations
- Modify: `config/serializer_groups/Provider.yaml`
- Create: `src/Controller/Api/CarrierProfileController.php`
- Create: `src/Controller/Api/AgentProfileController.php`
- Create: `migrations/mysql/Version20260623050000.php`
- Create: `migrations/sqlite/Version20260623050000.php`

**BO repo (`d:\Projects\make-cargo-client-bo`):**
- Create: `src/services/CarrierProfileService.js`
- Create: `src/services/AgentProfileService.js`
- Create: `src/config/enums/CarrierType.js`
- Create: `src/views/provider/CarrierProfile.vue`
- Create: `src/views/provider/AgentProfile.vue`
- Modify: `src/views/provider/ProviderDetail.vue` — add conditional tabs

---

### Task 1: CarrierType enum + CarrierProfile + AgentProfile entities + migration

**Files:**
- Create: `src/Misc/Enum/CarrierType.php`
- Create: `src/Entity/CarrierProfile.php`
- Create: `src/Entity/AgentProfile.php`
- Create: `src/Repository/CarrierProfileRepository.php`
- Create: `src/Repository/AgentProfileRepository.php`
- Modify: `src/Entity/Provider.php`
- Create: `migrations/mysql/Version20260623050000.php`
- Create: `migrations/sqlite/Version20260623050000.php`

- [ ] **Step 1: Create `src/Misc/Enum/CarrierType.php`**

```php
<?php
namespace App\Misc\Enum;

enum CarrierType: string
{
    case Ocean   = 'OCEAN';
    case Air     = 'AIR';
    case Road    = 'ROAD';
    case Rail    = 'RAIL';
    case Courier = 'COURIER';
    case Nvocc   = 'NVOCC';
}
```

- [ ] **Step 2: Create `src/Entity/CarrierProfile.php`**

```php
<?php
namespace App\Entity;

use App\Misc\Enum\CarrierType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\CarrierProfileRepository;

#[ORM\Entity(repositoryClass: CarrierProfileRepository::class)]
class CarrierProfile
{
    #[ORM\Id]
    #[ORM\OneToOne(inversedBy: 'carrierProfile')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Provider $provider = null;

    #[ORM\Column(length: 8, nullable: true)]
    private ?string $scacCode = null;

    #[ORM\Column(length: 4, nullable: true)]
    private ?string $iataCode = null;

    #[ORM\Column(length: 16, nullable: true, enumType: CarrierType::class)]
    private ?CarrierType $carrierType = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $alliance = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $bookingPlatform = null;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $bookingEmail = null;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $siEmail = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $amsFiler = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $preferredPayment = null;

    public function getProvider(): ?Provider { return $this->provider; }
    public function setProvider(Provider $provider): static { $this->provider = $provider; return $this; }

    public function getScacCode(): ?string { return $this->scacCode; }
    public function setScacCode(?string $scacCode): static { $this->scacCode = $scacCode; return $this; }

    public function getIataCode(): ?string { return $this->iataCode; }
    public function setIataCode(?string $iataCode): static { $this->iataCode = $iataCode; return $this; }

    public function getCarrierType(): ?CarrierType { return $this->carrierType; }
    public function setCarrierType(?CarrierType $carrierType): static { $this->carrierType = $carrierType; return $this; }

    public function getAlliance(): ?string { return $this->alliance; }
    public function setAlliance(?string $alliance): static { $this->alliance = $alliance; return $this; }

    public function getBookingPlatform(): ?string { return $this->bookingPlatform; }
    public function setBookingPlatform(?string $bookingPlatform): static { $this->bookingPlatform = $bookingPlatform; return $this; }

    public function getBookingEmail(): ?string { return $this->bookingEmail; }
    public function setBookingEmail(?string $bookingEmail): static { $this->bookingEmail = $bookingEmail; return $this; }

    public function getSiEmail(): ?string { return $this->siEmail; }
    public function setSiEmail(?string $siEmail): static { $this->siEmail = $siEmail; return $this; }

    public function getAmsFiler(): ?string { return $this->amsFiler; }
    public function setAmsFiler(?string $amsFiler): static { $this->amsFiler = $amsFiler; return $this; }

    public function getPreferredPayment(): ?string { return $this->preferredPayment; }
    public function setPreferredPayment(?string $preferredPayment): static { $this->preferredPayment = $preferredPayment; return $this; }
}
```

- [ ] **Step 3: Create `src/Entity/AgentProfile.php`**

```php
<?php
namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\AgentProfileRepository;

#[ORM\Entity(repositoryClass: AgentProfileRepository::class)]
class AgentProfile
{
    #[ORM\Id]
    #[ORM\OneToOne(inversedBy: 'agentProfile')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Provider $provider = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $network = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $agentCode = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $coverageCountries = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $modesHandled = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 6, scale: 4, nullable: true)]
    private ?string $commissionRate = null;

    #[ORM\Column(length: 3, nullable: true)]
    private ?string $settlementCurrency = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $settlementTerms = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 4, scale: 2, nullable: true)]
    private ?string $performanceScore = null;

    public function getProvider(): ?Provider { return $this->provider; }
    public function setProvider(Provider $provider): static { $this->provider = $provider; return $this; }

    public function getNetwork(): ?string { return $this->network; }
    public function setNetwork(?string $network): static { $this->network = $network; return $this; }

    public function getAgentCode(): ?string { return $this->agentCode; }
    public function setAgentCode(?string $agentCode): static { $this->agentCode = $agentCode; return $this; }

    public function getCoverageCountries(): ?array { return $this->coverageCountries; }
    public function setCoverageCountries(?array $coverageCountries): static { $this->coverageCountries = $coverageCountries; return $this; }

    public function getModesHandled(): ?array { return $this->modesHandled; }
    public function setModesHandled(?array $modesHandled): static { $this->modesHandled = $modesHandled; return $this; }

    public function getCommissionRate(): ?string { return $this->commissionRate; }
    public function setCommissionRate(?string $commissionRate): static { $this->commissionRate = $commissionRate; return $this; }

    public function getSettlementCurrency(): ?string { return $this->settlementCurrency; }
    public function setSettlementCurrency(?string $settlementCurrency): static { $this->settlementCurrency = $settlementCurrency; return $this; }

    public function getSettlementTerms(): ?string { return $this->settlementTerms; }
    public function setSettlementTerms(?string $settlementTerms): static { $this->settlementTerms = $settlementTerms; return $this; }

    public function getPerformanceScore(): ?string { return $this->performanceScore; }
    public function setPerformanceScore(?string $performanceScore): static { $this->performanceScore = $performanceScore; return $this; }
}
```

- [ ] **Step 4: Create repositories**

Create `src/Repository/CarrierProfileRepository.php`:

```php
<?php
namespace App\Repository;
use App\Entity\CarrierProfile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CarrierProfileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CarrierProfile::class);
    }

    public function save(CarrierProfile $entity): CarrierProfile
    {
        $this->getEntityManager()->persist($entity);
        $this->getEntityManager()->flush();
        return $entity;
    }
}
```

Create `src/Repository/AgentProfileRepository.php` with the same structure, replacing `CarrierProfile` with `AgentProfile`.

- [ ] **Step 5: Add OneToOne relations to `src/Entity/Provider.php`**

Add these imports:
```php
use App\Entity\CarrierProfile;
use App\Entity\AgentProfile;
```

Add these fields:

```php
    #[ORM\OneToOne(mappedBy: 'provider', cascade: ['persist', 'remove'])]
    private ?CarrierProfile $carrierProfile = null;

    #[ORM\OneToOne(mappedBy: 'provider', cascade: ['persist', 'remove'])]
    private ?AgentProfile $agentProfile = null;
```

Add getters/setters:

```php
    public function getCarrierProfile(): ?CarrierProfile { return $this->carrierProfile; }
    public function setCarrierProfile(?CarrierProfile $carrierProfile): static { $this->carrierProfile = $carrierProfile; return $this; }

    public function getAgentProfile(): ?AgentProfile { return $this->agentProfile; }
    public function setAgentProfile(?AgentProfile $agentProfile): static { $this->agentProfile = $agentProfile; return $this; }
```

- [ ] **Step 6: Create MySQL migration**

Create `migrations/mysql/Version20260623050000.php`:

```php
<?php
declare(strict_types=1);
namespace SqlEngineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260623050000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add carrier_profile and agent_profile tables (one-to-one extension of provider)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE carrier_profile (provider_id INT NOT NULL, scac_code VARCHAR(8) DEFAULT NULL, iata_code VARCHAR(4) DEFAULT NULL, carrier_type VARCHAR(16) DEFAULT NULL, alliance VARCHAR(32) DEFAULT NULL, booking_platform VARCHAR(64) DEFAULT NULL, booking_email VARCHAR(128) DEFAULT NULL, si_email VARCHAR(128) DEFAULT NULL, ams_filer VARCHAR(64) DEFAULT NULL, preferred_payment VARCHAR(32) DEFAULT NULL, PRIMARY KEY(provider_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE carrier_profile ADD CONSTRAINT FK_carrier_profile_provider FOREIGN KEY (provider_id) REFERENCES provider (id) ON DELETE CASCADE');

        $this->addSql('CREATE TABLE agent_profile (provider_id INT NOT NULL, network VARCHAR(64) DEFAULT NULL, agent_code VARCHAR(32) DEFAULT NULL, coverage_countries JSON DEFAULT NULL, modes_handled JSON DEFAULT NULL, commission_rate NUMERIC(6, 4) DEFAULT NULL, settlement_currency VARCHAR(3) DEFAULT NULL, settlement_terms VARCHAR(32) DEFAULT NULL, performance_score NUMERIC(4, 2) DEFAULT NULL, PRIMARY KEY(provider_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE agent_profile ADD CONSTRAINT FK_agent_profile_provider FOREIGN KEY (provider_id) REFERENCES provider (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE carrier_profile DROP FOREIGN KEY FK_carrier_profile_provider');
        $this->addSql('DROP TABLE carrier_profile');
        $this->addSql('ALTER TABLE agent_profile DROP FOREIGN KEY FK_agent_profile_provider');
        $this->addSql('DROP TABLE agent_profile');
    }
}
```

- [ ] **Step 7: Create SQLite migration**

Create `migrations/sqlite/Version20260623050000.php`:

```php
<?php
declare(strict_types=1);
namespace SqlEngineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260623050000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add carrier_profile and agent_profile tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE carrier_profile (provider_id INTEGER NOT NULL, scac_code VARCHAR(8) DEFAULT NULL, iata_code VARCHAR(4) DEFAULT NULL, carrier_type VARCHAR(16) DEFAULT NULL, alliance VARCHAR(32) DEFAULT NULL, booking_platform VARCHAR(64) DEFAULT NULL, booking_email VARCHAR(128) DEFAULT NULL, si_email VARCHAR(128) DEFAULT NULL, ams_filer VARCHAR(64) DEFAULT NULL, preferred_payment VARCHAR(32) DEFAULT NULL, PRIMARY KEY(provider_id), CONSTRAINT FK_carrier_profile_provider FOREIGN KEY (provider_id) REFERENCES provider (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE TABLE agent_profile (provider_id INTEGER NOT NULL, network VARCHAR(64) DEFAULT NULL, agent_code VARCHAR(32) DEFAULT NULL, coverage_countries CLOB DEFAULT NULL, modes_handled CLOB DEFAULT NULL, commission_rate NUMERIC(6,4) DEFAULT NULL, settlement_currency VARCHAR(3) DEFAULT NULL, settlement_terms VARCHAR(32) DEFAULT NULL, performance_score NUMERIC(4,2) DEFAULT NULL, PRIMARY KEY(provider_id), CONSTRAINT FK_agent_profile_provider FOREIGN KEY (provider_id) REFERENCES provider (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
    }

    public function down(Schema $schema): void {}
}
```

- [ ] **Step 8: Validate schema**

```bash
php bin/console doctrine:schema:validate --env=test
```

Expected: `[OK] The mapping files are correct.`

- [ ] **Step 9: Commit**

```bash
git add src/Misc/Enum/CarrierType.php src/Entity/CarrierProfile.php src/Entity/AgentProfile.php src/Repository/CarrierProfileRepository.php src/Repository/AgentProfileRepository.php src/Entity/Provider.php migrations/mysql/Version20260623050000.php migrations/sqlite/Version20260623050000.php
git commit -m "feat(ab-05): add CarrierProfile and AgentProfile entities as one-to-one extension of Provider"
```

---

### Task 2: API controllers + serializer for profiles

**Files:**
- Create: `src/Controller/Api/CarrierProfileController.php`
- Create: `src/Controller/Api/AgentProfileController.php`
- Modify: `config/serializer_groups/Provider.yaml`

- [ ] **Step 1: Create `src/Controller/Api/CarrierProfileController.php`**

```php
<?php
namespace App\Controller\Api;

use App\Entity\CarrierProfile;
use App\Entity\Provider;
use App\Repository\CarrierProfileRepository;
use App\Repository\ProviderRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/carrier-profile')]
#[IsGranted('ROLE_USER')]
class CarrierProfileController extends AbstractController
{
    public function __construct(
        private CarrierProfileRepository $repository,
        private ProviderRepository $providerRepository,
        private SerializerInterface $serializer,
    ) {}

    #[Route('/{providerId}', methods: ['GET'])]
    public function GET(int $providerId): JsonResponse
    {
        $profile = $this->repository->find($providerId);
        if (!$profile) {
            return $this->json(null);
        }
        return $this->json($this->serializer->normalize($profile, null, ['groups' => ['list']]));
    }

    #[Route('/{providerId}', methods: ['PUT'])]
    public function PUT(int $providerId, Request $request): JsonResponse
    {
        $profile = $this->repository->find($providerId);
        if (!$profile) {
            $provider = $this->providerRepository->find($providerId);
            if (!$provider) {
                return $this->json(['error' => 'Provider not found'], Response::HTTP_NOT_FOUND);
            }
            $profile = new CarrierProfile();
            $profile->setProvider($provider);
        }
        $data = $request->request->all();
        if (isset($data['scacCode'])) $profile->setScacCode($data['scacCode'] ?: null);
        if (isset($data['iataCode'])) $profile->setIataCode($data['iataCode'] ?: null);
        if (isset($data['carrierType'])) $profile->setCarrierType($data['carrierType'] ? \App\Misc\Enum\CarrierType::from($data['carrierType']) : null);
        if (isset($data['alliance'])) $profile->setAlliance($data['alliance'] ?: null);
        if (isset($data['bookingPlatform'])) $profile->setBookingPlatform($data['bookingPlatform'] ?: null);
        if (isset($data['bookingEmail'])) $profile->setBookingEmail($data['bookingEmail'] ?: null);
        if (isset($data['siEmail'])) $profile->setSiEmail($data['siEmail'] ?: null);
        if (isset($data['amsFiler'])) $profile->setAmsFiler($data['amsFiler'] ?: null);
        if (isset($data['preferredPayment'])) $profile->setPreferredPayment($data['preferredPayment'] ?: null);
        return $this->json($this->serializer->normalize($this->repository->save($profile), null, ['groups' => ['list']]));
    }
}
```

- [ ] **Step 2: Create `src/Controller/Api/AgentProfileController.php`**

Same structure as CarrierProfileController, handling the AgentProfile fields: `network`, `agentCode`, `coverageCountries` (JSON decode from string), `modesHandled` (JSON decode), `commissionRate`, `settlementCurrency`, `settlementTerms`, `performanceScore`.

```php
<?php
namespace App\Controller\Api;

use App\Entity\AgentProfile;
use App\Repository\AgentProfileRepository;
use App\Repository\ProviderRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/agent-profile')]
#[IsGranted('ROLE_USER')]
class AgentProfileController extends AbstractController
{
    public function __construct(
        private AgentProfileRepository $repository,
        private ProviderRepository $providerRepository,
        private SerializerInterface $serializer,
    ) {}

    #[Route('/{providerId}', methods: ['GET'])]
    public function GET(int $providerId): JsonResponse
    {
        $profile = $this->repository->find($providerId);
        return $this->json($profile ? $this->serializer->normalize($profile, null, ['groups' => ['list']]) : null);
    }

    #[Route('/{providerId}', methods: ['PUT'])]
    public function PUT(int $providerId, Request $request): JsonResponse
    {
        $profile = $this->repository->find($providerId);
        if (!$profile) {
            $provider = $this->providerRepository->find($providerId);
            if (!$provider) {
                return $this->json(['error' => 'Provider not found'], Response::HTTP_NOT_FOUND);
            }
            $profile = new AgentProfile();
            $profile->setProvider($provider);
        }
        $data = $request->request->all();
        if (isset($data['network'])) $profile->setNetwork($data['network'] ?: null);
        if (isset($data['agentCode'])) $profile->setAgentCode($data['agentCode'] ?: null);
        if (isset($data['coverageCountries'])) $profile->setCoverageCountries($data['coverageCountries'] ? json_decode($data['coverageCountries'], true) : null);
        if (isset($data['modesHandled'])) $profile->setModesHandled($data['modesHandled'] ? json_decode($data['modesHandled'], true) : null);
        if (isset($data['commissionRate'])) $profile->setCommissionRate($data['commissionRate'] ?: null);
        if (isset($data['settlementCurrency'])) $profile->setSettlementCurrency($data['settlementCurrency'] ?: null);
        if (isset($data['settlementTerms'])) $profile->setSettlementTerms($data['settlementTerms'] ?: null);
        if (isset($data['performanceScore'])) $profile->setPerformanceScore($data['performanceScore'] ?: null);
        return $this->json($this->serializer->normalize($this->repository->save($profile), null, ['groups' => ['list']]));
    }
}
```

- [ ] **Step 3: Add profile to Provider serializer**

In `config/serializer_groups/Provider.yaml`, add:

```yaml
        - carrierProfile
        - agentProfile
```

Also create serializer group files for the new entities:

`config/serializer_groups/CarrierProfile.yaml`:
```yaml
App\Entity\CarrierProfile:
    list:
        - scacCode
        - iataCode
        - carrierType
        - alliance
        - bookingPlatform
        - bookingEmail
        - siEmail
        - amsFiler
        - preferredPayment
```

`config/serializer_groups/AgentProfile.yaml`:
```yaml
App\Entity\AgentProfile:
    list:
        - network
        - agentCode
        - coverageCountries
        - modesHandled
        - commissionRate
        - settlementCurrency
        - settlementTerms
        - performanceScore
```

- [ ] **Step 4: Commit**

```bash
git add src/Controller/Api/CarrierProfileController.php src/Controller/Api/AgentProfileController.php config/serializer_groups/Provider.yaml config/serializer_groups/CarrierProfile.yaml config/serializer_groups/AgentProfile.yaml
git commit -m "feat(ab-05): add CarrierProfile and AgentProfile API controllers and serializer groups"
```

---

### Task 3: BO — profile views + conditional tabs on Provider detail

**Files:**
- Create: `src/services/CarrierProfileService.js` (BO repo)
- Create: `src/services/AgentProfileService.js` (BO repo)
- Create: `src/config/enums/CarrierType.js` (BO repo)
- Create: `src/views/provider/CarrierProfile.vue` (BO repo)
- Create: `src/views/provider/AgentProfile.vue` (BO repo)
- Modify: `src/views/provider/ProviderDetail.vue` (BO repo)

- [ ] **Step 1: Create `src/services/CarrierProfileService.js`**

```js
import { baseApiUrl } from '@/utils/api'
export default {
  get(providerId) { return $api(`carrier-profile/${providerId}`) },
  save(providerId, data) {
    return $api(`carrier-profile/${providerId}`, {
      method: 'PUT',
      body: (() => { const fd = new FormData(); Object.entries(data).forEach(([k,v]) => v != null && fd.append(k, v)); return fd })(),
      loading: true
    })
  }
}
```

Create `src/services/AgentProfileService.js` with the same structure, replacing `carrier-profile` with `agent-profile`.

- [ ] **Step 2: Create `src/config/enums/CarrierType.js`**

```js
function list() {
  return [
    { value: 'OCEAN',   title: $gettext('Ocean') },
    { value: 'AIR',     title: $gettext('Air') },
    { value: 'ROAD',    title: $gettext('Road') },
    { value: 'RAIL',    title: $gettext('Rail') },
    { value: 'COURIER', title: $gettext('Courier') },
    { value: 'NVOCC',   title: $gettext('NVOCC') },
  ]
}
export const getList = () => list()
export const findByValue = (value) => list().find(s => s.value === value) ?? null
```

- [ ] **Step 3: Create `src/views/provider/CarrierProfile.vue`**

```vue
<script setup>
import CarrierProfileService from '@/services/CarrierProfileService'
import { getList as carrierTypeList } from '@/config/enums/CarrierType'

const props = defineProps({ provider: { type: Object, required: true } })
const emit = defineEmits(['providerChanged'])
const profile = ref(null)
const form = ref({
  scacCode: null, iataCode: null, carrierType: null, alliance: null,
  bookingPlatform: null, bookingEmail: null, siEmail: null,
  amsFiler: null, preferredPayment: null,
})
const saving = ref(false)

onMounted(async () => {
  profile.value = await CarrierProfileService.get(props.provider.id)
  if (profile.value) Object.assign(form.value, profile.value)
})

async function save() {
  saving.value = true
  await CarrierProfileService.save(props.provider.id, form.value)
  saving.value = false
  emit('providerChanged')
}
</script>
<template>
<VCard class="mt-4">
  <VCardTitle>{{ $gettext('Carrier Profile') }}</VCardTitle>
  <VCardText>
    <VRow>
      <VCol cols="12" md="3"><VTextField v-model="form.scacCode" :label="$gettext('SCAC Code')" /></VCol>
      <VCol cols="12" md="3"><VTextField v-model="form.iataCode" :label="$gettext('IATA Code')" /></VCol>
      <VCol cols="12" md="3">
        <VSelect v-model="form.carrierType" :label="$gettext('Carrier Type')" :items="carrierTypeList()" item-value="value" item-title="title" clearable />
      </VCol>
      <VCol cols="12" md="3"><VTextField v-model="form.alliance" :label="$gettext('Alliance')" /></VCol>
      <VCol cols="12" md="4"><VTextField v-model="form.bookingPlatform" :label="$gettext('Booking Platform')" /></VCol>
      <VCol cols="12" md="4"><VTextField v-model="form.bookingEmail" :label="$gettext('Booking Email')" /></VCol>
      <VCol cols="12" md="4"><VTextField v-model="form.siEmail" :label="$gettext('SI Email')" /></VCol>
      <VCol cols="12" md="4"><VTextField v-model="form.amsFiler" :label="$gettext('AMS Filer')" /></VCol>
      <VCol cols="12" md="4"><VTextField v-model="form.preferredPayment" :label="$gettext('Preferred Payment')" /></VCol>
    </VRow>
  </VCardText>
  <VCardActions>
    <VSpacer />
    <VBtn color="primary" :loading="saving" @click="save">{{ $gettext('Save') }}</VBtn>
  </VCardActions>
</VCard>
</template>
```

- [ ] **Step 4: Create `src/views/provider/AgentProfile.vue`**

```vue
<script setup>
import AgentProfileService from '@/services/AgentProfileService'

const props = defineProps({ provider: { type: Object, required: true } })
const emit = defineEmits(['providerChanged'])
const form = ref({
  network: null, agentCode: null, coverageCountries: null, modesHandled: null,
  commissionRate: null, settlementCurrency: null, settlementTerms: null, performanceScore: null,
})
const saving = ref(false)

onMounted(async () => {
  const profile = await AgentProfileService.get(props.provider.id)
  if (profile) Object.assign(form.value, {
    ...profile,
    coverageCountries: profile.coverageCountries ? JSON.stringify(profile.coverageCountries) : null,
    modesHandled: profile.modesHandled ? JSON.stringify(profile.modesHandled) : null,
  })
})

async function save() {
  saving.value = true
  await AgentProfileService.save(props.provider.id, form.value)
  saving.value = false
  emit('providerChanged')
}
</script>
<template>
<VCard class="mt-4">
  <VCardTitle>{{ $gettext('Agent Profile') }}</VCardTitle>
  <VCardText>
    <VRow>
      <VCol cols="12" md="4"><VTextField v-model="form.network" :label="$gettext('Network (WCA, FIATA...)')" /></VCol>
      <VCol cols="12" md="4"><VTextField v-model="form.agentCode" :label="$gettext('Agent Code')" /></VCol>
      <VCol cols="12" md="4"><VTextField v-model="form.settlementCurrency" :label="$gettext('Settlement Currency')" /></VCol>
      <VCol cols="12" md="4"><VTextField v-model="form.settlementTerms" :label="$gettext('Settlement Terms')" /></VCol>
      <VCol cols="12" md="4"><VTextField v-model="form.commissionRate" :label="$gettext('Commission Rate (%)')" type="number" /></VCol>
      <VCol cols="12" md="4"><VTextField v-model="form.performanceScore" :label="$gettext('Performance Score (0-5)')" type="number" /></VCol>
      <VCol cols="12"><VTextarea v-model="form.coverageCountries" :label="$gettext('Coverage Countries (JSON array, e.g. [\"VN\",\"TH\"])')" rows="2" /></VCol>
      <VCol cols="12"><VTextarea v-model="form.modesHandled" :label="$gettext('Modes Handled (JSON array, e.g. [\"OCN\",\"AIR\"])')" rows="2" /></VCol>
    </VRow>
  </VCardText>
  <VCardActions>
    <VSpacer />
    <VBtn color="primary" :loading="saving" @click="save">{{ $gettext('Save') }}</VBtn>
  </VCardActions>
</VCard>
</template>
```

- [ ] **Step 5: Add conditional tabs to `src/views/provider/ProviderDetail.vue`**

Open `src/views/provider/ProviderDetail.vue`. Find the `tabs` array (or wherever the provider detail tabs are defined). Add two conditional tab entries:

```js
import CarrierProfile from './CarrierProfile.vue'
import AgentProfile from './AgentProfile.vue'
```

In the tabs computed/array, add after existing tabs:

```js
(['CA', 'AL'].includes(props.provider?.type)) ? {
  title: $gettext('Carrier Profile'),
  icon: 'tabler-ship',
  component: CarrierProfile,
  value: 'carrier-profile',
} : null,
(props.provider?.type === 'AG') ? {
  title: $gettext('Agent Profile'),
  icon: 'tabler-world',
  component: AgentProfile,
  value: 'agent-profile',
} : null,
```

Filter out nulls: `.filter(Boolean)`.

- [ ] **Step 6: Commit**

```bash
git add src/services/CarrierProfileService.js src/services/AgentProfileService.js src/config/enums/CarrierType.js src/views/provider/CarrierProfile.vue src/views/provider/AgentProfile.vue src/views/provider/ProviderDetail.vue
git commit -m "feat(ab-05): add CarrierProfile and AgentProfile views as conditional tabs on Provider detail"
```
