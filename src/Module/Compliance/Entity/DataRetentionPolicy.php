<?php
declare(strict_types=1);
namespace App\Module\Compliance\Entity;

use App\Module\Compliance\Repository\DataRetentionPolicyRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DataRetentionPolicyRepository::class)]
#[ORM\Table(name: 'data_retention_policy')]
class DataRetentionPolicy
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64)]
    private string $dataCategory;

    #[ORM\Column(type: 'smallint')]
    private int $retentionYears;

    #[ORM\Column(type: 'text')]
    private string $legalBasis;

    #[ORM\Column(type: 'text')]
    private string $appliesTo;

    #[ORM\Column(type: 'boolean')]
    private bool $autoDelete = false;

    #[ORM\Column]
    private \DateTime $createdAt;

    public function getId(): ?int { return $this->id; }

    public function getDataCategory(): string { return $this->dataCategory; }
    public function setDataCategory(string $v): static { $this->dataCategory = $v; return $this; }

    public function getRetentionYears(): int { return $this->retentionYears; }
    public function setRetentionYears(int $v): static { $this->retentionYears = $v; return $this; }

    public function getLegalBasis(): string { return $this->legalBasis; }
    public function setLegalBasis(string $v): static { $this->legalBasis = $v; return $this; }

    public function getAppliesTo(): string { return $this->appliesTo; }
    public function setAppliesTo(string $v): static { $this->appliesTo = $v; return $this; }

    public function isAutoDelete(): bool { return $this->autoDelete; }
    public function setAutoDelete(bool $v): static { $this->autoDelete = $v; return $this; }

    public function getCreatedAt(): \DateTime { return $this->createdAt; }
    public function setCreatedAt(\DateTime $v): static { $this->createdAt = $v; return $this; }

    public function toArray(): array
    {
        return [
            'id'             => $this->id,
            'dataCategory'   => $this->dataCategory,
            'retentionYears' => $this->retentionYears,
            'legalBasis'     => $this->legalBasis,
            'appliesTo'      => $this->appliesTo,
            'autoDelete'     => $this->autoDelete,
            'createdAt'      => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}
