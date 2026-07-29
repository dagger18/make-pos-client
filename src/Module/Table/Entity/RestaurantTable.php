<?php
namespace App\Module\Table\Entity;

use App\Misc\Traits\EntityDateTimeAbleTrait;
use App\Module\Core\Entity\Location;
use App\Module\Table\Enum\TableStatus;
use App\Module\Table\Repository\RestaurantTableRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RestaurantTableRepository::class)]
#[ORM\Table(name: 'restaurant_table')]
#[ORM\HasLifecycleCallbacks]
class RestaurantTable
{
    use EntityDateTimeAbleTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Location::class)]
    #[ORM\JoinColumn(name: 'location_id', nullable: false)]
    private ?Location $location = null;

    #[ORM\Column(length: 64)]
    private string $name = '';

    #[ORM\Column(nullable: true)]
    private ?int $capacity = null;

    #[ORM\Column(length: 16, enumType: TableStatus::class)]
    private TableStatus $status = TableStatus::Available;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    public function getId(): ?int { return $this->id; }

    public function getLocation(): ?Location { return $this->location; }
    public function setLocation(?Location $v): static { $this->location = $v; return $this; }

    public function getName(): string { return $this->name; }
    public function setName(string $v): static { $this->name = $v; return $this; }

    public function getCapacity(): ?int { return $this->capacity; }
    public function setCapacity(?int $v): static { $this->capacity = $v; return $this; }

    public function getStatus(): TableStatus { return $this->status; }
    public function setStatus(TableStatus $v): static { $this->status = $v; return $this; }

    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $v): static { $this->notes = $v; return $this; }

    public function toArray(): array
    {
        return [
            'id'       => $this->id,
            'name'     => $this->name,
            'capacity' => $this->capacity,
            'status'   => $this->status->value,
            'notes'    => $this->notes,
        ];
    }
}
