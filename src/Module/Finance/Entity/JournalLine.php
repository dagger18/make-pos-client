<?php
namespace App\Module\Finance\Entity;

use App\Module\Finance\Repository\JournalLineRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: JournalLineRepository::class)]
class JournalLine
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'lines')]
    #[ORM\JoinColumn(nullable: false)]
    private ?JournalEntry $journalEntry = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?ChartOfAccount $account = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 4)]
    private float $debit = 0;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 4)]
    private float $credit = 0;

    #[ORM\Column(length: 8)]
    private string $currency = '';

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 4)]
    private float $baseDebit = 0;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 4)]
    private float $baseCredit = 0;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 6)]
    private float $fxRate = 1;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    public function getId(): ?int { return $this->id; }

    public function getJournalEntry(): ?JournalEntry { return $this->journalEntry; }
    public function setJournalEntry(?JournalEntry $e): static { $this->journalEntry = $e; return $this; }

    public function getAccount(): ?ChartOfAccount { return $this->account; }
    public function setAccount(?ChartOfAccount $a): static { $this->account = $a; return $this; }

    public function getDebit(): float { return $this->debit; }
    public function setDebit(float $v): static { $this->debit = $v; return $this; }

    public function getCredit(): float { return $this->credit; }
    public function setCredit(float $v): static { $this->credit = $v; return $this; }

    public function getCurrency(): string { return $this->currency; }
    public function setCurrency(string $c): static { $this->currency = $c; return $this; }

    public function getBaseDebit(): float { return $this->baseDebit; }
    public function setBaseDebit(float $v): static { $this->baseDebit = $v; return $this; }

    public function getBaseCredit(): float { return $this->baseCredit; }
    public function setBaseCredit(float $v): static { $this->baseCredit = $v; return $this; }

    public function getFxRate(): float { return $this->fxRate; }
    public function setFxRate(float $v): static { $this->fxRate = $v; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $d): static { $this->description = $d; return $this; }
}
