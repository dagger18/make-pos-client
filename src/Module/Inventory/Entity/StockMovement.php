<?php
namespace App\Module\Inventory\Entity;

use App\Misc\Traits\EntityDateTimeAbleTrait;
use App\Module\Core\Entity\User;
use App\Module\Inventory\Enum\MovementType;
use App\Module\Inventory\Repository\StockMovementRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StockMovementRepository::class)]
#[ORM\Table(name: 'stock_movement')]
#[ORM\HasLifecycleCallbacks]
class StockMovement
{
    use EntityDateTimeAbleTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: StockLevel::class, inversedBy: 'movements')]
    #[ORM\JoinColumn(name: 'stock_level_id', nullable: false, onDelete: 'CASCADE')]
    private ?StockLevel $stockLevel = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by_id', nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\Column(length: 16, enumType: MovementType::class)]
    private MovementType $type = MovementType::Adjustment;

    #[ORM\Column]
    private int $quantityDelta = 0;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    public function getId(): ?int { return $this->id; }

    public function getStockLevel(): ?StockLevel { return $this->stockLevel; }
    public function setStockLevel(?StockLevel $v): static { $this->stockLevel = $v; return $this; }

    public function getCreatedBy(): ?User { return $this->createdBy; }
    public function setCreatedBy(?User $v): static { $this->createdBy = $v; return $this; }

    public function getType(): MovementType { return $this->type; }
    public function setType(MovementType $v): static { $this->type = $v; return $this; }

    public function getQuantityDelta(): int { return $this->quantityDelta; }
    public function setQuantityDelta(int $v): static { $this->quantityDelta = $v; return $this; }

    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $v): static { $this->notes = $v; return $this; }

    public function toArray(): array
    {
        return [
            'id'            => $this->id,
            'type'          => $this->type->value,
            'quantityDelta' => $this->quantityDelta,
            'notes'         => $this->notes,
            'createdAt'     => $this->createdDate?->format('Y-m-d H:i:s'),
            'createdBy'     => $this->createdBy ? [
                'id'   => $this->createdBy->getId(),
                'name' => $this->createdBy->getFullName(),
            ] : null,
        ];
    }
}
