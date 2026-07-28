<?php

namespace App\Module\Finance\Entity;

use App\Module\Core\Entity\SubEntity;

use Doctrine\ORM\Mapping as ORM;
use App\Module\Finance\Repository\BankAccountRepository;
use App\Misc\Traits\EntityDateTimeAbleTrait;

#[ORM\Entity(repositoryClass: BankAccountRepository::class)]
#[ORM\HasLifecycleCallbacks]
class BankAccount implements SubEntity
{
    use EntityDateTimeAbleTrait;
    
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $bankName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $branch = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $swiftCode = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $accountName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $accountNumber = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBankName(): ?string
    {
        return $this->bankName;
    }

    public function setBankName(?string $bankName): static
    {
        $this->bankName = $bankName;

        return $this;
    }

    public function getBranch(): ?string
    {
        return $this->branch;
    }

    public function setBranch(?string $branch): static
    {
        $this->branch = $branch;

        return $this;
    }

    public function getSwiftCode(): ?string
    {
        return $this->swiftCode;
    }

    public function setSwiftCode(?string $swiftCode): static
    {
        $this->swiftCode = $swiftCode;

        return $this;
    }

    public function getAccountName(): ?string
    {
        return $this->accountName;
    }

    public function setAccountName(?string $accountName): static
    {
        $this->accountName = $accountName;

        return $this;
    }

    public function getAccountNumber(): ?string
    {
        return $this->accountNumber;
    }

    public function setAccountNumber(?string $accountNumber): static
    {
        $this->accountNumber = $accountNumber;

        return $this;
    }
}
