<?php

namespace App\Module\Carrier\Entity;

use App\Module\Core\Entity\Media;
use App\Module\Core\Entity\Money;
use App\Module\Finance\Entity\BankAccount;
use App\Module\Finance\Entity\InvoiceInfo;
use App\Module\Crm\Entity\Contact;
use App\Module\Crm\Entity\Partner;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use App\Module\Core\Entity\User;
use App\Module\Finance\Enum\CreditStatus;
use App\Module\Carrier\Enum\ProviderType;
use App\Module\Core\Enum\TransportType;
use Doctrine\ORM\Mapping as ORM;
use App\Module\Carrier\Repository\ProviderRepository;
use App\Misc\Attribute\NullableEmbeddable;
use App\Misc\Traits\EntityDateTimeAbleTrait;
use App\Misc\Attribute\ContainsNullableEmbeddable;
use App\Module\Carrier\Entity\CarrierProfile;
use App\Module\Crm\Entity\AgentProfile;
use App\Misc\Attribute\MediaProperty;

#[ORM\Entity(repositoryClass: ProviderRepository::class)]
#[ContainsNullableEmbeddable]
#[ORM\HasLifecycleCallbacks]
class Provider extends Partner
{
    use EntityDateTimeAbleTrait;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $code = null;

    #[ORM\Column(type: "string", length: 2, enumType: ProviderType::class)]
    private ProviderType $type;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $province = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $city = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $zipCode = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $country = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $taxNumber = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $trackingUrl = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $creditPeriod = null;

    #[ORM\Embedded(class: Money::class)]
    #[NullableEmbeddable]
    private ?Money $creditLimit;

    #[ORM\Column(length: 16, enumType: CreditStatus::class)]
    private CreditStatus $creditStatus = CreditStatus::Active;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $creditHoldReason = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $creditReviewedAt = null;

    #[ORM\ManyToOne]
    private ?User $creditReviewedBy = null;

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    #[MediaProperty]
    private ?Media $logo = null;

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?BankAccount $defaultBankAccount = null;

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?InvoiceInfo $defaultInvoiceInfo = null;

    #[ORM\ManyToMany(targetEntity: Media::class, cascade: ['persist'])]
    #[MediaProperty]
    private Collection $documents;

    #[ORM\ManyToMany(targetEntity: BankAccount::class, cascade: ['persist'])]
    private Collection $bankAccounts;

    #[ORM\ManyToMany(targetEntity: InvoiceInfo::class, cascade:['persist'])]
    private Collection $invoiceInfos;

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?Contact $defaultContact = null;

    #[ORM\ManyToMany(targetEntity: Contact::class, cascade: ['persist'])]
    private Collection $contacts;

    #[ORM\ManyToOne()]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $createdBy = null;

    #[ORM\ManyToMany(targetEntity: User::class)]
    private Collection $assignedUsers;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $note = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(type: Types::SIMPLE_ARRAY, enumType: TransportType::class, nullable: true)]
    private array $transportTypes = [];

    #[ORM\OneToOne(mappedBy: 'provider', cascade: ['persist', 'remove'])]
    private ?CarrierProfile $carrierProfile = null;

    #[ORM\OneToOne(mappedBy: 'provider', cascade: ['persist', 'remove'])]
    private ?AgentProfile $agentProfile = null;

    public function __construct()
    {
        $this->documents = new ArrayCollection();
        $this->bankAccounts = new ArrayCollection();
        $this->invoiceInfos = new ArrayCollection();
        $this->contacts = new ArrayCollection();
        $this->assignedUsers = new ArrayCollection();
    }
    
    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getType(): ProviderType
    {
        return $this->type;
    }

    public function setType(ProviderType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getProvince(): ?string
    {
        return $this->province;
    }

    public function setProvince(?string $province): static
    {
        $this->province = $province;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): static
    {
        $this->city = $city;

        return $this;
    }

    public function getZipCode(): ?string
    {
        return $this->zipCode;
    }

    public function setZipCode(?string $zipCode): static
    {
        $this->zipCode = $zipCode;

        return $this;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(?string $country): static
    {
        $this->country = $country;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getTaxNumber(): ?string
    {
        return $this->taxNumber;
    }

    public function setTaxNumber(?string $taxNumber): static
    {
        $this->taxNumber = $taxNumber;

        return $this;
    }

    public function getTrackingUrl(): ?string
    {
        return $this->trackingUrl;
    }

    public function setTrackingUrl(?string $trackingUrl): static
    {
        $this->trackingUrl = $trackingUrl;

        return $this;
    }

    public function getCreditPeriod(): ?int
    {
        return $this->creditPeriod;
    }

    public function setCreditPeriod(?int $creditPeriod): static
    {
        $this->creditPeriod = $creditPeriod;

        return $this;
    }

    public function getCreditLimit(): ?Money 
    {
        return $this->creditLimit;
    }

    public function setCreditLimit(?Money $creditLimit): static
    {
        $this->creditLimit = $creditLimit;

        return $this;
    }

    public function getLogo(): ?Media
    {
        return $this->logo;
    }

    public function setLogo(?Media $logo): static
    {
        $this->logo = $logo;

        return $this;
    }

    public function getDefaultBankAccount(): ?BankAccount
    {
        return $this->defaultBankAccount;
    }

    public function setDefaultBankAccount(BankAccount $defaultBankAccount): static
    {
        $this->defaultBankAccount = $defaultBankAccount;

        return $this;
    }

    public function getDefaultInvoiceInfo(): ?InvoiceInfo
    {
        return $this->defaultInvoiceInfo;
    }

    public function setDefaultInvoiceInfo(InvoiceInfo $defaultInvoiceInfo): static
    {
        $this->defaultInvoiceInfo = $defaultInvoiceInfo;

        return $this;
    }

    /**
     * @return Collection<int, Media>
     */
    public function getDocuments(): Collection
    {
        return $this->documents;
    }

    public function addDocument(Media $document): static
    {
        if (!$this->documents->contains($document)) {
            $this->documents->add($document);
        }

        return $this;
    }

    public function removeDocument(Media $document): static
    {
        $this->documents->removeElement($document);

        return $this;
    }

    /**
     * @return Collection<int, BankAccount>
     */
    public function getBankAccounts(): Collection
    {
        return $this->bankAccounts;
    }

    public function addBankAccount(BankAccount $bankAccount): static
    {
        if (!$this->bankAccounts->contains($bankAccount)) {
            $this->bankAccounts->add($bankAccount);
        }

        return $this;
    }

    public function removeBankAccount(BankAccount $bankAccount): static
    {
        $this->bankAccounts->removeElement($bankAccount);

        return $this;
    }

    /**
     * @return Collection<int, InvoiceInfo>
     */
    public function getInvoiceInfos(): Collection
    {
        return $this->invoiceInfos;
    }

    public function addInvoiceInfo(InvoiceInfo $invoiceInfo): static
    {
        if (!$this->invoiceInfos->contains($invoiceInfo)) {
            $this->invoiceInfos->add($invoiceInfo);
        }

        return $this;
    }

    public function removeInvoiceInfo(InvoiceInfo $invoiceInfo): static
    {
        $this->invoiceInfos->removeElement($invoiceInfo);

        return $this;
    }

    public function getDefaultContact(): ?Contact
    {
        return $this->defaultContact;
    }

    public function setDefaultContact(Contact $defaultContact): static
    {
        $this->defaultContact = $defaultContact;

        return $this;
    }

    /**
     * @return Collection<int, Contact>
     */
    public function getContacts(): Collection
    {
        return $this->contacts;
    }

    public function addContact(Contact $contact): static
    {
        if (!$this->contacts->contains($contact)) {
            $this->contacts->add($contact);
        }

        return $this;
    }

    public function removeContact(Contact $contact): static
    {
        $this->contacts->removeElement($contact);

        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $this->note = $note;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getAssignedUsers(): Collection
    {
        return $this->assignedUsers;
    }

    public function addAssignedUser(User $assignedUser): static
    {
        if (!$this->assignedUsers->contains($assignedUser)) {
            $this->assignedUsers->add($assignedUser);
        }
        return $this;
    }

    public function removeAssignedUser(User $assignedUser): static
    {
        $this->assignedUsers->removeElement($assignedUser);
        return $this;
    }

    public function getTransportTypes(): array
    {
        return $this->transportTypes;
    }

    public function setTransportTypes(array $transportTypes): static
    {
        $this->transportTypes = array_values(array_filter(array_map(
            fn($v) => $v instanceof TransportType ? $v : TransportType::tryFrom((string) $v),
            $transportTypes
        )));
        return $this;
    }

    public function getCreditStatus(): CreditStatus
    {
        return $this->creditStatus;
    }

    public function setCreditStatus(CreditStatus $creditStatus): static
    {
        $this->creditStatus = $creditStatus;

        return $this;
    }

    public function getCreditHoldReason(): ?string
    {
        return $this->creditHoldReason;
    }

    public function setCreditHoldReason(?string $creditHoldReason): static
    {
        $this->creditHoldReason = $creditHoldReason;

        return $this;
    }

    public function getCreditReviewedAt(): ?\DateTimeInterface
    {
        return $this->creditReviewedAt;
    }

    public function setCreditReviewedAt(?\DateTimeInterface $creditReviewedAt): static
    {
        $this->creditReviewedAt = $creditReviewedAt;

        return $this;
    }

    public function getCreditReviewedBy(): ?User
    {
        return $this->creditReviewedBy;
    }

    public function setCreditReviewedBy(?User $creditReviewedBy): static
    {
        $this->creditReviewedBy = $creditReviewedBy;

        return $this;
    }

    public function getCarrierProfile(): ?CarrierProfile
    {
        return $this->carrierProfile;
    }

    public function setCarrierProfile(?CarrierProfile $carrierProfile): static
    {
        $this->carrierProfile = $carrierProfile;

        return $this;
    }

    public function getAgentProfile(): ?AgentProfile
    {
        return $this->agentProfile;
    }

    public function setAgentProfile(?AgentProfile $agentProfile): static
    {
        $this->agentProfile = $agentProfile;

        return $this;
    }
}
