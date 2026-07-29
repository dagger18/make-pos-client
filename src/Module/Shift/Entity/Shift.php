<?php
namespace App\Module\Shift\Entity;

use App\Misc\Traits\EntityDateTimeAbleTrait;
use App\Module\Core\Entity\Location;
use App\Module\Core\Entity\User;
use App\Module\Shift\Enum\ShiftStatus;
use App\Module\Shift\Repository\ShiftRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ShiftRepository::class)]
#[ORM\Table(name: 'shift')]
#[ORM\HasLifecycleCallbacks]
class Shift
{
    use EntityDateTimeAbleTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Location::class)]
    #[ORM\JoinColumn(name: 'location_id', nullable: false)]
    private ?Location $location = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'cashier_id', nullable: true, onDelete: 'SET NULL')]
    private ?User $cashier = null;

    #[ORM\Column(length: 8, enumType: ShiftStatus::class)]
    private ShiftStatus $status = ShiftStatus::Open;

    #[ORM\Column(type: Types::DECIMAL, precision: 20, scale: 6)]
    private string $openingAmount = '0.000000';

    #[ORM\Column(type: Types::DECIMAL, precision: 20, scale: 6, nullable: true)]
    private ?string $closingAmount = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTime $openedAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $closedAt = null;

    public function __construct()
    {
        $this->openedAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getLocation(): ?Location { return $this->location; }
    public function setLocation(?Location $v): static { $this->location = $v; return $this; }

    public function getCashier(): ?User { return $this->cashier; }
    public function setCashier(?User $v): static { $this->cashier = $v; return $this; }

    public function getStatus(): ShiftStatus { return $this->status; }
    public function setStatus(ShiftStatus $v): static { $this->status = $v; return $this; }

    public function getOpeningAmount(): string { return $this->openingAmount; }
    public function setOpeningAmount(string $v): static { $this->openingAmount = $v; return $this; }

    public function getClosingAmount(): ?string { return $this->closingAmount; }
    public function setClosingAmount(?string $v): static { $this->closingAmount = $v; return $this; }

    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $v): static { $this->notes = $v; return $this; }

    public function getOpenedAt(): \DateTime { return $this->openedAt; }
    public function setOpenedAt(\DateTime $v): static { $this->openedAt = $v; return $this; }

    public function getClosedAt(): ?\DateTime { return $this->closedAt; }
    public function setClosedAt(?\DateTime $v): static { $this->closedAt = $v; return $this; }

    public function toArray(): array
    {
        return [
            'id'             => $this->id,
            'status'         => $this->status->value,
            'openingAmount'  => $this->openingAmount,
            'closingAmount'  => $this->closingAmount,
            'notes'          => $this->notes,
            'openedAt'       => $this->openedAt->format('Y-m-d H:i:s'),
            'closedAt'       => $this->closedAt?->format('Y-m-d H:i:s'),
            'cashier'        => $this->cashier ? [
                'id'   => $this->cashier->getId(),
                'name' => $this->cashier->getFullName(),
            ] : null,
        ];
    }
}
