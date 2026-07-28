<?php

namespace App\Module\Crm\Entity;

use App\Module\Core\Entity\Media;
use App\Module\Core\Entity\User;
use App\Module\Finance\Entity\BankAccount;
use App\Module\Finance\Entity\InvoiceInfo;

use App\Module\Core\Entity\Money;
use App\Module\Crm\Enum\ClientTier;
use App\Module\Crm\Enum\ClientType;
use App\Module\Finance\Enum\CreditStatus;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Module\Crm\Repository\ClientRepository;
use App\Module\Crm\Enum\ClientResidenceType;
use App\Module\Crm\Enum\ClientCustomInfoMode;
use App\Misc\Attribute\NullableEmbeddable;
use Doctrine\Common\Collections\Collection;
use App\Misc\Traits\EntityDateTimeAbleTrait;
use Doctrine\Common\Collections\ArrayCollection;
use App\Misc\Attribute\ContainsNullableEmbeddable;
use App\Misc\Attribute\MediaProperty;

#[ORM\Entity(repositoryClass: ClientRepository::class)]
#[ContainsNullableEmbeddable]
#[ORM\HasLifecycleCallbacks]
class Client extends Partner
{
    use EntityDateTimeAbleTrait;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $city = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $province = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $zipCode = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $country = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $tradingName = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $taxNumber = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $registrationNo = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $website = null;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $establishmentDate = null;

    #[ORM\Column(length: 1, enumType: ClientType::class)]
    private ?ClientType $type;

    #[ORM\Column(length: 16, nullable: true, enumType: ClientTier::class)]
    private ?ClientTier $tier = null;

    #[ORM\Column(length: 1, nullable: true, enumType: ClientResidenceType::class)]
    private ?ClientResidenceType $residenceType;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $note = null;

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?InvoiceInfo $defaultInvoiceInfo = null;

    #[ORM\ManyToMany(targetEntity: InvoiceInfo::class, cascade: ['persist'])]
    private Collection $invoiceInfos;

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?BankAccount $defaultBankAccount = null;

    #[ORM\ManyToMany(targetEntity: BankAccount::class, cascade: ['persist'])]
    private Collection $bankAccounts;

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?Contact $defaultContact = null;

    #[ORM\ManyToMany(targetEntity: Contact::class, cascade: ['persist'])]
    private Collection $contacts;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $creditPeriod = null;

    #[ORM\Embedded(class: Money::class)]
    #[NullableEmbeddable]
    private ?Money $creditLimit;

    #[ORM\Embedded(class: Money::class)]
    #[NullableEmbeddable]
    private ?Money $unpaid;

    #[ORM\Column(length: 16, enumType: CreditStatus::class)]
    private CreditStatus $creditStatus = CreditStatus::Active;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $creditHoldReason = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $creditReviewedAt = null;

    #[ORM\ManyToOne]
    private ?User $creditReviewedBy = null;

    #[ORM\Column(length: 1, enumType: ClientCustomInfoMode::class)]
    private ?ClientCustomInfoMode $customInfoMode;

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    private ?InvoiceInfo $customInfo = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $createdBy = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $accountManager = null;

    #[ORM\ManyToMany(targetEntity: Media::class, cascade: ['persist'])]
    #[MediaProperty]
    private Collection $documents;

    #[ORM\ManyToMany(targetEntity: User::class)]
    private Collection $assignedUsers;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $code = null;

    public function __construct()
    {
        $this->invoiceInfos = new ArrayCollection();
        $this->bankAccounts = new ArrayCollection();
        $this->contacts = new ArrayCollection();
        $this->documents = new ArrayCollection();
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

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): static
    {
        $this->address = $address;

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

    public function getProvince(): ?string
    {
        return $this->province;
    }

    public function setProvince(?string $province): static
    {
        $this->province = $province;

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

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;

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

    public function getTradingName(): ?string
    {
        return $this->tradingName;
    }

    public function setTradingName(?string $tradingName): static
    {
        $this->tradingName = $tradingName;

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

    public function getRegistrationNo(): ?string
    {
        return $this->registrationNo;
    }

    public function setRegistrationNo(?string $registrationNo): static
    {
        $this->registrationNo = $registrationNo;

        return $this;
    }

    public function getWebsite(): ?string
    {
        return $this->website;
    }

    public function setWebsite(?string $website): static
    {
        $this->website = $website;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getEstablishmentDate(): ?\DateTimeInterface
    {
        return $this->establishmentDate;
    }

    public function setEstablishmentDate(?\DateTimeInterface $establishmentDate): static
    {
        $this->establishmentDate = $establishmentDate;

        return $this;
    }

    public function getType(): ?ClientType
    {
        return $this->type;
    }

    public function setType(ClientType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getResidenceType(): ?ClientResidenceType
    {
        return $this->residenceType;
    }

    public function setResidenceType(?ClientResidenceType $residenceType): static
    {
        $this->residenceType = $residenceType;

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

    public function getDefaultBankAccount(): ?BankAccount
    {
        return $this->defaultBankAccount;
    }

    public function setDefaultBankAccount(BankAccount $defaultBankAccount): static
    {
        $this->defaultBankAccount = $defaultBankAccount;

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

    public function getUnpaid(): ?Money 
    {
        return $this->unpaid;
    }

    public function setUnpaid(?Money $unpaid): static
    {
        $this->unpaid = $unpaid;

        return $this;
    }

    public function getCustomInfoMode(): ?ClientCustomInfoMode
    {
        return $this->customInfoMode;
    }

    public function setCustomInfoMode(ClientCustomInfoMode $customInfoMode): static
    {
        $this->customInfoMode = $customInfoMode;

        return $this;
    }

    public function getCustomInfo(): ?InvoiceInfo
    {
        return $this->customInfo;
    }

    public function setCustomInfo(?InvoiceInfo $customInfo): static
    {
        $this->customInfo = $customInfo;

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

    public function getAccountManager(): ?User
    {
        return $this->accountManager;
    }

    public function setAccountManager(?User $accountManager): static
    {
        $this->accountManager = $accountManager;

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

    public function getCustomPriceMarkup(): ?PriceMarkup
    {
        return $this->customPriceMarkup;
    }

    public function setCustomPriceMarkup(?PriceMarkup $customPriceMarkup): static
    {
        $this->customPriceMarkup = $customPriceMarkup;

        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): static
    {
        $this->code = $code;

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

    public function getTier(): ?ClientTier
    {
        return $this->tier;
    }

    public function setTier(?ClientTier $tier): static
    {
        $this->tier = $tier;

        return $this;
    }
}
