<?php
namespace App\Module\Finance\Entity;

use App\Module\Core\Entity\User;

use App\Module\Finance\Repository\JournalEntryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: JournalEntryRepository::class)]
#[ORM\HasLifecycleCallbacks]
class JournalEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64, unique: true)]
    private string $journalNumber = '';

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?EbitNote $ebitNote = null;

    #[ORM\Column(length: 32)]
    private string $sourceType = ''; // AR_INVOICE, AP_BILL, AR_PAYMENT, AP_PAYMENT, CREDIT_NOTE, MANUAL

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $entryDate = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private bool $isPosted = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $postedAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $postedBy = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\OneToMany(mappedBy: 'journalEntry', targetEntity: JournalLine::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $lines;

    public function __construct()
    {
        $this->lines = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void { $this->createdAt = new \DateTime(); }

    public function getId(): ?int { return $this->id; }

    public function getJournalNumber(): string { return $this->journalNumber; }
    public function setJournalNumber(string $n): static { $this->journalNumber = $n; return $this; }

    public function getEbitNote(): ?EbitNote { return $this->ebitNote; }
    public function setEbitNote(?EbitNote $e): static { $this->ebitNote = $e; return $this; }

    public function getSourceType(): string { return $this->sourceType; }
    public function setSourceType(string $t): static { $this->sourceType = $t; return $this; }

    public function getEntryDate(): ?\DateTimeInterface { return $this->entryDate; }
    public function setEntryDate(\DateTimeInterface $d): static { $this->entryDate = $d; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $d): static { $this->description = $d; return $this; }

    public function isPosted(): bool { return $this->isPosted; }
    public function setIsPosted(bool $v): static { $this->isPosted = $v; return $this; }

    public function getPostedAt(): ?\DateTimeInterface { return $this->postedAt; }
    public function setPostedAt(?\DateTimeInterface $d): static { $this->postedAt = $d; return $this; }

    public function getPostedBy(): ?User { return $this->postedBy; }
    public function setPostedBy(?User $u): static { $this->postedBy = $u; return $this; }

    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }

    /** @return Collection<int, JournalLine> */
    public function getLines(): Collection { return $this->lines; }

    public function addLine(JournalLine $line): static
    {
        if (!$this->lines->contains($line)) {
            $this->lines->add($line);
            $line->setJournalEntry($this);
        }
        return $this;
    }
}
