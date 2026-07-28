<?php
namespace App\Module\Finance\Entity;

use App\Module\Finance\Repository\ChartOfAccountRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ChartOfAccountRepository::class)]
class ChartOfAccount
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 16, unique: true)]
    private string $code = '';

    #[ORM\Column(length: 128)]
    private string $name = '';

    #[ORM\Column(length: 16)]
    private string $accountType = ''; // ASSET, LIABILITY, REVENUE, COST, OTHER

    #[ORM\Column]
    private bool $isActive = true;

    public function getId(): ?int { return $this->id; }

    public function getCode(): string { return $this->code; }
    public function setCode(string $c): static { $this->code = $c; return $this; }

    public function getName(): string { return $this->name; }
    public function setName(string $n): static { $this->name = $n; return $this; }

    public function getAccountType(): string { return $this->accountType; }
    public function setAccountType(string $t): static { $this->accountType = $t; return $this; }

    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $v): static { $this->isActive = $v; return $this; }
}
