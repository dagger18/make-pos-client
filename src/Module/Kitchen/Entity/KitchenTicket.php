<?php
namespace App\Module\Kitchen\Entity;

use App\Misc\Traits\EntityDateTimeAbleTrait;
use App\Module\Kitchen\Enum\KitchenStatus;
use App\Module\Kitchen\Repository\KitchenTicketRepository;
use App\Module\Sales\Entity\Order;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: KitchenTicketRepository::class)]
#[ORM\Table(name: 'kitchen_ticket')]
#[ORM\HasLifecycleCallbacks]
class KitchenTicket
{
    use EntityDateTimeAbleTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Order::class)]
    #[ORM\JoinColumn(name: 'order_id', nullable: false, onDelete: 'CASCADE')]
    private ?Order $order = null;

    #[ORM\Column(length: 16, enumType: KitchenStatus::class)]
    private KitchenStatus $status = KitchenStatus::Pending;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    public function getId(): ?int { return $this->id; }

    public function getOrder(): ?Order { return $this->order; }
    public function setOrder(?Order $v): static { $this->order = $v; return $this; }

    public function getStatus(): KitchenStatus { return $this->status; }
    public function setStatus(KitchenStatus $v): static { $this->status = $v; return $this; }

    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $v): static { $this->notes = $v; return $this; }

    public function toArray(): array
    {
        $order = $this->order;
        return [
            'id'        => $this->id,
            'status'    => $this->status->value,
            'notes'     => $this->notes,
            'createdAt' => $this->createdDate?->format('Y-m-d H:i:s'),
            'order'     => $order ? [
                'id'    => $order->getId(),
                'notes' => $order->getNotes(),
                'items' => array_map(fn ($i) => [
                    'productName' => $i->getProductName(),
                    'quantity'    => $i->getQuantity(),
                    'notes'       => $i->getNotes(),
                ], $order->getItems()->toArray()),
            ] : null,
        ];
    }
}
