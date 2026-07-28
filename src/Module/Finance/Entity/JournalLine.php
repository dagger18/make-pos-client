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
    private string $debit = '0';

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 4)]
    private string $credit = '0';

    #[ORM\Column(length: 8)]
    private string $currency = '';

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 4)]
    private string $baseDebit = '0';

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 4)]
    private string $baseCredit = '0';

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 6)]
    private string $fxRate = '1';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    public function getId(): ?int { return $this->id; }

    public function getJournalEntry(): ?JournalEntry { return $this->journalEntry; }
    public function setJournalEntry(?JournalEntry $e): static { $this->journalEntry = $e; return $this; }

    public function getAccount(): ?ChartOfAccount { return $this->account; }
    public function setAccount(?ChartOfAccount $a): static { $this->account = $a; return $this; }

    public function getDebit(): float { return (float) $this->debit; }
    public function setDebit(float $v): static { $this->debit = (string) $v; return $this; }

    public function getCredit(): float { return (float) $this->credit; }
    public function setCredit(float $v): static { $this->credit = (string) $v; return $this; }

    public function getCurrency(): string { return $this->currency; }
    public function setCurrency(string $c): static { $this->currency = $c; return $this; }

    public function getBaseDebit(): float { return (float) $this->baseDebit; }
    public function setBaseDebit(float $v): static { $this->baseDebit = (string) $v; return $this; }

    public function getBaseCredit(): float { return (float) $this->baseCredit; }
    public function setBaseCredit(float $v): static { $this->baseCredit = (string) $v; return $this; }

    public function getFxRate(): float { return (float) $this->fxRate; }
    public function setFxRate(float $v): static { $this->fxRate = (string) $v; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $d): static { $this->description = $d; return $this; }
}
