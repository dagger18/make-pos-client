# AB-06: Multiple Addresses Per Organisation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add an `OrganisationAddress` entity allowing both Clients and Providers to have multiple labelled addresses (REGISTERED / BILLING / WAREHOUSE / PICKUP / DELIVERY), with one marked as default per type. Expose via a REST API and a shared Addresses.vue component used by both Client and Provider detail views.

**Architecture:** A single `OrganisationAddress` entity has nullable `client` and `provider` FK columns (exactly one of the two is non-null per row). A new `AddressType` enum defines the categories. The API controller is a standalone CRUD controller filtered by `clientId` or `providerId`. The BO reuses the same `Addresses.vue` component in both the Client and Provider detail tabs, following the same pattern as the existing `BankAccounts.vue` and `Contacts.vue` components.

**Tech Stack:** PHP 8.2, Symfony 6, Doctrine ORM, MySQL + SQLite migrations, Vue 3 + Vuetify (BO)

---

## File Structure

**API repo (`d:\Projects\make-cargo-client`):**
- Create: `src/Misc/Enum/AddressType.php`
- Create: `src/Entity/OrganisationAddress.php`
- Create: `src/Repository/OrganisationAddressRepository.php`
- Create: `src/Controller/Api/OrganisationAddressController.php`
- Create: `migrations/mysql/Version20260623060000.php`
- Create: `migrations/sqlite/Version20260623060000.php`

**BO repo (`d:\Projects\make-cargo-client-bo`):**
- Create: `src/config/enums/AddressType.js`
- Create: `src/services/OrganisationAddressService.js`
- Create: `src/views/provider/Addresses.vue`
- Modify: `src/views/client/ClientDetail.vue` — add Addresses tab
- Modify: `src/views/provider/ProviderDetail.vue` — add Addresses tab

---

### Task 1: AddressType enum + OrganisationAddress entity + migration

**Files:**
- Create: `src/Misc/Enum/AddressType.php`
- Create: `src/Entity/OrganisationAddress.php`
- Create: `src/Repository/OrganisationAddressRepository.php`
- Create: `migrations/mysql/Version20260623060000.php`
- Create: `migrations/sqlite/Version20260623060000.php`

- [ ] **Step 1: Create `src/Misc/Enum/AddressType.php`**

```php
<?php
namespace App\Misc\Enum;

enum AddressType: string
{
    case Registered = 'REGISTERED';
    case Billing    = 'BILLING';
    case Warehouse  = 'WAREHOUSE';
    case Pickup     = 'PICKUP';
    case Delivery   = 'DELIVERY';
}
```

- [ ] **Step 2: Create `src/Entity/OrganisationAddress.php`**

```php
<?php
namespace App\Entity;

use App\Misc\Enum\AddressType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\OrganisationAddressRepository;

#[ORM\Entity(repositoryClass: OrganisationAddressRepository::class)]
#[ORM\HasLifecycleCallbacks]
class OrganisationAddress
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Client $client = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Provider $provider = null;

    #[ORM\Column(length: 32, enumType: AddressType::class)]
    private AddressType $addressType = AddressType::Registered;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $label = null;

    #[ORM\Column(length: 255)]
    private string $addressLine1 = '';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $addressLine2 = null;

    #[ORM\Column(length: 128)]
    private string $city = '';

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $state = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $postalCode = null;

    #[ORM\Column(length: 2)]
    private string $country = '';

    #[ORM\Column]
    private bool $isDefault = false;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getClient(): ?Client { return $this->client; }
    public function setClient(?Client $client): static { $this->client = $client; return $this; }

    public function getProvider(): ?Provider { return $this->provider; }
    public function setProvider(?Provider $provider): static { $this->provider = $provider; return $this; }

    public function getAddressType(): AddressType { return $this->addressType; }
    public function setAddressType(AddressType $addressType): static { $this->addressType = $addressType; return $this; }

    public function getLabel(): ?string { return $this->label; }
    public function setLabel(?string $label): static { $this->label = $label; return $this; }

    public function getAddressLine1(): string { return $this->addressLine1; }
    public function setAddressLine1(string $addressLine1): static { $this->addressLine1 = $addressLine1; return $this; }

    public function getAddressLine2(): ?string { return $this->addressLine2; }
    public function setAddressLine2(?string $addressLine2): static { $this->addressLine2 = $addressLine2; return $this; }

    public function getCity(): string { return $this->city; }
    public function setCity(string $city): static { $this->city = $city; return $this; }

    public function getState(): ?string { return $this->state; }
    public function setState(?string $state): static { $this->state = $state; return $this; }

    public function getPostalCode(): ?string { return $this->postalCode; }
    public function setPostalCode(?string $postalCode): static { $this->postalCode = $postalCode; return $this; }

    public function getCountry(): string { return $this->country; }
    public function setCountry(string $country): static { $this->country = $country; return $this; }

    public function isDefault(): bool { return $this->isDefault; }
    public function setIsDefault(bool $isDefault): static { $this->isDefault = $isDefault; return $this; }

    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $notes): static { $this->notes = $notes; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
}
```

- [ ] **Step 3: Create `src/Repository/OrganisationAddressRepository.php`**

```php
<?php
namespace App\Repository;

use App\Entity\OrganisationAddress;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class OrganisationAddressRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrganisationAddress::class);
    }

    public function findByClient(int $clientId): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.client = :clientId')
            ->setParameter('clientId', $clientId)
            ->orderBy('a.addressType', 'ASC')
            ->addOrderBy('a.isDefault', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByProvider(int $providerId): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.provider = :providerId')
            ->setParameter('providerId', $providerId)
            ->orderBy('a.addressType', 'ASC')
            ->addOrderBy('a.isDefault', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function save(OrganisationAddress $entity): OrganisationAddress
    {
        $this->getEntityManager()->persist($entity);
        $this->getEntityManager()->flush();
        return $entity;
    }
}
```

- [ ] **Step 4: Create MySQL migration**

Create `migrations/mysql/Version20260623060000.php`:

```php
<?php
declare(strict_types=1);
namespace SqlEngineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260623060000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create organisation_address table for multiple addresses per client/provider';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE organisation_address (id INT AUTO_INCREMENT NOT NULL, client_id INT DEFAULT NULL, provider_id INT DEFAULT NULL, address_type VARCHAR(32) NOT NULL, label VARCHAR(64) DEFAULT NULL, address_line1 VARCHAR(255) NOT NULL, address_line2 VARCHAR(255) DEFAULT NULL, city VARCHAR(128) NOT NULL, state VARCHAR(128) DEFAULT NULL, postal_code VARCHAR(32) DEFAULT NULL, country VARCHAR(2) NOT NULL, is_default TINYINT(1) NOT NULL DEFAULT 0, notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, INDEX IDX_org_addr_client (client_id), INDEX IDX_org_addr_provider (provider_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE organisation_address ADD CONSTRAINT FK_org_addr_client FOREIGN KEY (client_id) REFERENCES client (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE organisation_address ADD CONSTRAINT FK_org_addr_provider FOREIGN KEY (provider_id) REFERENCES provider (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE organisation_address DROP FOREIGN KEY FK_org_addr_client');
        $this->addSql('ALTER TABLE organisation_address DROP FOREIGN KEY FK_org_addr_provider');
        $this->addSql('DROP TABLE organisation_address');
    }
}
```

- [ ] **Step 5: Create SQLite migration**

Create `migrations/sqlite/Version20260623060000.php`:

```php
<?php
declare(strict_types=1);
namespace SqlEngineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260623060000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create organisation_address table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE organisation_address (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, client_id INTEGER DEFAULT NULL, provider_id INTEGER DEFAULT NULL, address_type VARCHAR(32) NOT NULL, label VARCHAR(64) DEFAULT NULL, address_line1 VARCHAR(255) NOT NULL, address_line2 VARCHAR(255) DEFAULT NULL, city VARCHAR(128) NOT NULL, state VARCHAR(128) DEFAULT NULL, postal_code VARCHAR(32) DEFAULT NULL, country VARCHAR(2) NOT NULL, is_default INTEGER NOT NULL DEFAULT 0, notes CLOB DEFAULT NULL, created_at DATETIME NOT NULL, CONSTRAINT FK_org_addr_client FOREIGN KEY (client_id) REFERENCES client (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_org_addr_provider FOREIGN KEY (provider_id) REFERENCES provider (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_org_addr_client ON organisation_address (client_id)');
        $this->addSql('CREATE INDEX IDX_org_addr_provider ON organisation_address (provider_id)');
    }

    public function down(Schema $schema): void {}
}
```

- [ ] **Step 6: Validate schema**

```bash
php bin/console doctrine:schema:validate --env=test
```

Expected: `[OK] The mapping files are correct.`

- [ ] **Step 7: Commit**

```bash
git add src/Misc/Enum/AddressType.php src/Entity/OrganisationAddress.php src/Repository/OrganisationAddressRepository.php migrations/mysql/Version20260623060000.php migrations/sqlite/Version20260623060000.php
git commit -m "feat(ab-06): add OrganisationAddress entity with client/provider FK and migration"
```

---

### Task 2: API controller for OrganisationAddress

**Files:**
- Create: `src/Controller/Api/OrganisationAddressController.php`

- [ ] **Step 1: Create `src/Controller/Api/OrganisationAddressController.php`**

```php
<?php
namespace App\Controller\Api;

use App\Entity\OrganisationAddress;
use App\Misc\Enum\AddressType;
use App\Repository\ClientRepository;
use App\Repository\OrganisationAddressRepository;
use App\Repository\ProviderRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/organisation-address')]
#[IsGranted('ROLE_USER')]
class OrganisationAddressController extends AbstractController
{
    public function __construct(
        private OrganisationAddressRepository $repository,
        private ClientRepository $clientRepository,
        private ProviderRepository $providerRepository,
        private SerializerInterface $serializer,
    ) {}

    #[Route('', methods: ['GET'])]
    public function LIST(Request $request): JsonResponse
    {
        $clientId = $request->query->getInt('clientId', 0);
        $providerId = $request->query->getInt('providerId', 0);
        $addresses = $clientId
            ? $this->repository->findByClient($clientId)
            : $this->repository->findByProvider($providerId);
        return $this->json($this->serializer->normalize($addresses, null, ['groups' => ['list']]));
    }

    #[Route('', methods: ['POST'])]
    public function POST(Request $request): JsonResponse
    {
        $data = $request->request->all();
        $address = new OrganisationAddress();
        $this->hydrateFromRequest($address, $data, $request);
        return $this->json(
            $this->serializer->normalize($this->repository->save($address), null, ['groups' => ['list']]),
            Response::HTTP_CREATED
        );
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function PUT(int $id, Request $request): JsonResponse
    {
        $address = $this->repository->find($id);
        if (!$address) {
            return $this->json(['error' => 'Not found'], Response::HTTP_NOT_FOUND);
        }
        $this->hydrateFromRequest($address, $request->request->all(), $request);
        return $this->json($this->serializer->normalize($this->repository->save($address), null, ['groups' => ['list']]));
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function DELETE(int $id): JsonResponse
    {
        $address = $this->repository->find($id);
        if (!$address) {
            return $this->json(['error' => 'Not found'], Response::HTTP_NOT_FOUND);
        }
        $em = $this->repository->getEntityManager();
        $em->remove($address);
        $em->flush();
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    private function hydrateFromRequest(OrganisationAddress $address, array $data, Request $request): void
    {
        if (!empty($data['clientId'])) {
            $address->setClient($this->clientRepository->find((int)$data['clientId']));
        }
        if (!empty($data['providerId'])) {
            $address->setProvider($this->providerRepository->find((int)$data['providerId']));
        }
        if (isset($data['addressType'])) $address->setAddressType(AddressType::from($data['addressType']));
        if (isset($data['label'])) $address->setLabel($data['label'] ?: null);
        if (isset($data['addressLine1'])) $address->setAddressLine1($data['addressLine1']);
        if (isset($data['addressLine2'])) $address->setAddressLine2($data['addressLine2'] ?: null);
        if (isset($data['city'])) $address->setCity($data['city']);
        if (isset($data['state'])) $address->setState($data['state'] ?: null);
        if (isset($data['postalCode'])) $address->setPostalCode($data['postalCode'] ?: null);
        if (isset($data['country'])) $address->setCountry($data['country']);
        if (isset($data['isDefault'])) $address->setIsDefault((bool)$data['isDefault']);
        if (isset($data['notes'])) $address->setNotes($data['notes'] ?: null);
    }
}
```

Create `config/serializer_groups/OrganisationAddress.yaml`:

```yaml
App\Entity\OrganisationAddress:
    list:
        - id
        - addressType
        - label
        - addressLine1
        - addressLine2
        - city
        - state
        - postalCode
        - country
        - isDefault
        - notes
        - createdAt
```

- [ ] **Step 2: Commit**

```bash
git add src/Controller/Api/OrganisationAddressController.php config/serializer_groups/OrganisationAddress.yaml
git commit -m "feat(ab-06): add OrganisationAddress API controller (CRUD, filterable by clientId/providerId)"
```

---

### Task 3: BO — Addresses.vue + tabs on Client and Provider detail

**Files:**
- Create: `src/config/enums/AddressType.js` (BO repo)
- Create: `src/services/OrganisationAddressService.js` (BO repo)
- Create: `src/views/provider/Addresses.vue` (BO repo)
- Modify: `src/views/client/ClientDetail.vue` (BO repo)
- Modify: `src/views/provider/ProviderDetail.vue` (BO repo)

- [ ] **Step 1: Create `src/config/enums/AddressType.js`**

```js
function list() {
  return [
    { value: 'REGISTERED', title: $gettext('Registered') },
    { value: 'BILLING',    title: $gettext('Billing') },
    { value: 'WAREHOUSE',  title: $gettext('Warehouse') },
    { value: 'PICKUP',     title: $gettext('Pickup') },
    { value: 'DELIVERY',   title: $gettext('Delivery') },
  ]
}
export const getList = () => list()
export const findByValue = (value) => list().find(s => s.value === value) ?? null
```

- [ ] **Step 2: Create `src/services/OrganisationAddressService.js`**

```js
import CommonService from '@/services/CommonService'
const BASE_URI = 'organisation-address'

export default {
  listByClient(clientId) { return $api(`${BASE_URI}?clientId=${clientId}`) },
  listByProvider(providerId) { return $api(`${BASE_URI}?providerId=${providerId}`) },
  add(data) { return $api(BASE_URI, { method: 'POST', body: CommonService.formData(data), loading: true }) },
  update(id, data) { return $api(`${BASE_URI}/${id}`, { method: 'PUT', body: CommonService.formData(data), loading: true }) },
  delete(id) { return $api(`${BASE_URI}/${id}`, { method: 'DELETE', loading: true }) },
}
```

- [ ] **Step 3: Create `src/views/provider/Addresses.vue`**

This component is shared by both Client and Provider. It accepts `clientId` or `providerId` as a prop.

```vue
<script setup>
import { getList as addressTypeList, findByValue as findAddressType } from '@/config/enums/AddressType'
import OrganisationAddressService from '@/services/OrganisationAddressService'

const props = defineProps({
  clientId: { type: Number, default: null },
  providerId: { type: Number, default: null },
})

const addresses = ref([])
const dialogOpen = ref(false)
const deleteDialogOpen = ref(false)
const editingId = ref(null)
const deleteId = ref(null)
const form = ref({
  addressType: 'REGISTERED', label: null, addressLine1: '', addressLine2: null,
  city: '', state: null, postalCode: null, country: '', isDefault: false, notes: null,
})

async function load() {
  addresses.value = props.clientId
    ? await OrganisationAddressService.listByClient(props.clientId)
    : await OrganisationAddressService.listByProvider(props.providerId)
}

onMounted(load)

function openCreate() {
  editingId.value = null
  form.value = { addressType: 'REGISTERED', label: null, addressLine1: '', addressLine2: null, city: '', state: null, postalCode: null, country: '', isDefault: false, notes: null }
  dialogOpen.value = true
}

function openEdit(addr) {
  editingId.value = addr.id
  form.value = { ...addr }
  dialogOpen.value = true
}

async function save() {
  const payload = {
    ...form.value,
    clientId: props.clientId,
    providerId: props.providerId,
  }
  if (editingId.value) {
    await OrganisationAddressService.update(editingId.value, payload)
  } else {
    await OrganisationAddressService.add(payload)
  }
  dialogOpen.value = false
  await load()
}

function confirmDelete(id) { deleteId.value = id; deleteDialogOpen.value = true }
async function doDelete() {
  await OrganisationAddressService.delete(deleteId.value)
  deleteDialogOpen.value = false
  await load()
}
</script>
<template>
<div>
  <div class="d-flex justify-end mb-3">
    <VBtn size="small" color="primary" prepend-icon="tabler-plus" @click="openCreate">{{ $gettext('Add Address') }}</VBtn>
  </div>

  <VCard v-for="addr in addresses" :key="addr.id" class="mb-3">
    <VCardText class="d-flex justify-space-between align-start">
      <div>
        <div class="d-flex align-center gap-2 mb-1">
          <VChip size="x-small" color="primary">{{ findAddressType(addr.addressType)?.title ?? addr.addressType }}</VChip>
          <VChip v-if="addr.isDefault" size="x-small" color="success">{{ $gettext('Default') }}</VChip>
          <span v-if="addr.label" class="font-weight-bold">{{ addr.label }}</span>
        </div>
        <div>{{ addr.addressLine1 }}</div>
        <div v-if="addr.addressLine2">{{ addr.addressLine2 }}</div>
        <div>{{ [addr.city, addr.state, addr.postalCode].filter(Boolean).join(', ') }} {{ addr.country }}</div>
        <div v-if="addr.notes" class="text-caption text-medium-emphasis mt-1">{{ addr.notes }}</div>
      </div>
      <div>
        <VBtn size="x-small" variant="text" icon="tabler-pencil" @click="openEdit(addr)" />
        <VBtn size="x-small" variant="text" icon="tabler-trash" color="error" @click="confirmDelete(addr.id)" />
      </div>
    </VCardText>
  </VCard>

  <p v-if="!addresses.length" class="text-medium-emphasis text-center py-4">{{ $gettext('No addresses added yet.') }}</p>

  <VDialog v-model="dialogOpen" max-width="600">
    <VCard :title="editingId ? $gettext('Edit Address') : $gettext('Add Address')">
      <VCardText>
        <VRow>
          <VCol cols="12" md="6">
            <VSelect v-model="form.addressType" :label="$gettext('Type')" :items="addressTypeList()" item-value="value" item-title="title" />
          </VCol>
          <VCol cols="12" md="6">
            <VTextField v-model="form.label" :label="$gettext('Label (e.g. HCM Warehouse)')" />
          </VCol>
          <VCol cols="12">
            <VTextField v-model="form.addressLine1" :label="$gettext('Address Line 1')" />
          </VCol>
          <VCol cols="12">
            <VTextField v-model="form.addressLine2" :label="$gettext('Address Line 2')" />
          </VCol>
          <VCol cols="12" md="4"><VTextField v-model="form.city" :label="$gettext('City')" /></VCol>
          <VCol cols="12" md="4"><VTextField v-model="form.state" :label="$gettext('State/Province')" /></VCol>
          <VCol cols="12" md="2"><VTextField v-model="form.postalCode" :label="$gettext('Postal Code')" /></VCol>
          <VCol cols="12" md="2"><VTextField v-model="form.country" :label="$gettext('Country')" maxlength="2" /></VCol>
          <VCol cols="12">
            <VTextarea v-model="form.notes" :label="$gettext('Notes (access instructions, hours...)')" rows="2" />
          </VCol>
          <VCol cols="12">
            <VCheckbox v-model="form.isDefault" :label="$gettext('Set as default')" />
          </VCol>
        </VRow>
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn variant="text" @click="dialogOpen = false">{{ $gettext('Cancel') }}</VBtn>
        <VBtn color="primary" @click="save">{{ $gettext('Save') }}</VBtn>
      </VCardActions>
    </VCard>
  </VDialog>

  <VDialog v-model="deleteDialogOpen" max-width="400">
    <VCard :title="$gettext('Delete Address')">
      <VCardText>{{ $gettext('Are you sure you want to delete this address?') }}</VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn variant="text" @click="deleteDialogOpen = false">{{ $gettext('Cancel') }}</VBtn>
        <VBtn color="error" @click="doDelete">{{ $gettext('Delete') }}</VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</div>
</template>
```

- [ ] **Step 4: Add Addresses tab to ClientDetail and ProviderDetail**

In `src/views/client/ClientDetail.vue`, find the tabs array. Import `Addresses` and add:

```js
import Addresses from '@/views/provider/Addresses.vue'
```

```js
{
  title: $gettext('Addresses'),
  icon: 'tabler-map-pin',
  value: 'addresses',
  component: Addresses,
}
```

In the window item for this tab, pass `:clientId="client.id"` instead of the generic `:entity` prop:

```html
<Addresses v-if="currentTab === 'addresses'" :clientId="client.id" />
```

Do the same for `src/views/provider/ProviderDetail.vue` but pass `:providerId="provider.id"`.

- [ ] **Step 5: Commit**

```bash
git add src/config/enums/AddressType.js src/services/OrganisationAddressService.js src/views/provider/Addresses.vue src/views/client/ClientDetail.vue src/views/provider/ProviderDetail.vue
git commit -m "feat(ab-06): add Addresses.vue and Addresses tab to Client and Provider detail views"
```
