<?php
namespace App\Module\Crm\Entity;

use App\Module\Core\Entity\Location;
use App\Module\Core\Entity\User;
use App\Module\Crm\Repository\SalesTargetRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SalesTargetRepository::class)]
class SalesTarget
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $salesRep;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $periodYear;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $periodMonth = null;

    #[ORM\Column(length: 32)]
    private string $targetType;
    // REVENUE / PROFIT / NEW_CUSTOMERS / QUOTES_SENT

    #[ORM\Column(type: Types::DECIMAL, precision: 20, scale: 6)]
    private string $targetValue;

    #[ORM\Column(length: 3, nullable: true)]
    private ?string $currency = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'location_id', nullable: true, onDelete: 'SET NULL')]
    private ?Location $location = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    public function getId(): ?int { return $this->id; }
    public function getSalesRep(): User { return $this->salesRep; }
    public function setSalesRep(User $v): static { $this->salesRep = $v; return $this; }
    public function getPeriodYear(): int { return $this->periodYear; }
    public function setPeriodYear(int $v): static { $this->periodYear = $v; return $this; }
    public function getPeriodMonth(): ?int { return $this->periodMonth; }
    public function setPeriodMonth(?int $v): static { $this->periodMonth = $v; return $this; }
    public function getTargetType(): string { return $this->targetType; }
    public function setTargetType(string $v): static { $this->targetType = $v; return $this; }
    public function getTargetValue(): float { return (float) $this->targetValue; }
    public function setTargetValue(float $v): static { $this->targetValue = (string) $v; return $this; }
    public function getCurrency(): ?string { return $this->currency; }
    public function setCurrency(?string $v): static { $this->currency = $v; return $this; }
    public function getLocation(): ?Location { return $this->location; }
    public function setLocation(?Location $v): static { $this->location = $v; return $this; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(\DateTimeInterface $v): static { $this->createdAt = $v; return $this; }

    public function toArray(): array
    {
        return [
            'id'          => $this->id,
            'salesRep'    => ['id' => $this->salesRep->getId(), 'name' => $this->salesRep->getName()],
            'periodYear'  => $this->periodYear,
            'periodMonth' => $this->periodMonth,
            'targetType'  => $this->targetType,
            'targetValue' => $this->getTargetValue(),
            'currency'    => $this->currency,
            'location'    => $this->location ? ['id' => $this->location->getId(), 'name' => $this->location->getName()] : null,
            'createdAt'   => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}
