<?php
namespace App\Module\Sales\Entity;

use App\Misc\Traits\EntityDateTimeAbleTrait;
use App\Module\Core\Entity\Location;
use App\Module\Core\Entity\User;
use App\Module\Sales\Enum\OrderStatus;
use App\Module\Sales\Repository\OrderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: 'pos_order')]
#[ORM\HasLifecycleCallbacks]
class Order
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
    #[ORM\JoinColumn(name: 'created_by_id', nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\Column(length: 16, enumType: OrderStatus::class)]
    private OrderStatus $status = OrderStatus::Open;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'decimal', precision: 20, scale: 6)]
    private string $subtotal = '0.000000';

    #[ORM\Column(type: 'decimal', precision: 20, scale: 6)]
    private string $discountAmount = '0.000000';

    #[ORM\Column(type: 'decimal', precision: 20, scale: 6)]
    private string $taxAmount = '0.000000';

    #[ORM\Column(type: 'decimal', precision: 20, scale: 6)]
    private string $total = '0.000000';

    #[ORM\Column(type: 'decimal', precision: 20, scale: 6)]
    private string $paidAmount = '0.000000';

    #[ORM\OneToMany(targetEntity: OrderItem::class, mappedBy: 'order', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $items;

    #[ORM\OneToMany(targetEntity: OrderPayment::class, mappedBy: 'order', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $payments;

    public function __construct()
    {
        $this->items    = new ArrayCollection();
        $this->payments = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getLocation(): ?Location { return $this->location; }
    public function setLocation(?Location $v): static { $this->location = $v; return $this; }

    public function getCreatedBy(): ?User { return $this->createdBy; }
    public function setCreatedBy(?User $v): static { $this->createdBy = $v; return $this; }

    public function getStatus(): OrderStatus { return $this->status; }
    public function setStatus(OrderStatus $v): static { $this->status = $v; return $this; }

    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $v): static { $this->notes = $v; return $this; }

    public function getSubtotal(): string { return $this->subtotal; }
    public function setSubtotal(string|float $v): static { $this->subtotal = (string) $v; return $this; }

    public function getDiscountAmount(): string { return $this->discountAmount; }
    public function setDiscountAmount(string|float $v): static { $this->discountAmount = (string) $v; return $this; }

    public function getTaxAmount(): string { return $this->taxAmount; }
    public function setTaxAmount(string|float $v): static { $this->taxAmount = (string) $v; return $this; }

    public function getTotal(): string { return $this->total; }
    public function setTotal(string|float $v): static { $this->total = (string) $v; return $this; }

    public function getPaidAmount(): string { return $this->paidAmount; }
    public function setPaidAmount(string|float $v): static { $this->paidAmount = (string) $v; return $this; }

    public function getItems(): Collection { return $this->items; }
    public function addItem(OrderItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setOrder($this);
        }
        return $this;
    }

    public function getPayments(): Collection { return $this->payments; }
    public function addPayment(OrderPayment $payment): static
    {
        if (!$this->payments->contains($payment)) {
            $this->payments->add($payment);
            $payment->setOrder($this);
        }
        return $this;
    }

    public function toArray(): array
    {
        return [
            'id'             => $this->id,
            'status'         => $this->status->value,
            'notes'          => $this->notes,
            'subtotal'       => (float) $this->subtotal,
            'discountAmount' => (float) $this->discountAmount,
            'taxAmount'      => (float) $this->taxAmount,
            'total'          => (float) $this->total,
            'paidAmount'     => (float) $this->paidAmount,
            'createdAt'      => $this->createdDate?->format('Y-m-d H:i:s'),
            'createdBy'      => $this->createdBy ? [
                'id'   => $this->createdBy->getId(),
                'name' => $this->createdBy->getFullName(),
            ] : null,
            'items'    => array_map(fn($i) => $i->toArray(), $this->items->toArray()),
            'payments' => array_map(fn($p) => $p->toArray(), $this->payments->toArray()),
        ];
    }
}
