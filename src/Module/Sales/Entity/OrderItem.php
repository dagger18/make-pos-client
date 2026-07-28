<?php
namespace App\Module\Sales\Entity;

use App\Module\Sales\Repository\OrderItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrderItemRepository::class)]
#[ORM\Table(name: 'order_item')]
class OrderItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'items')]
    #[ORM\JoinColumn(name: 'order_id', nullable: false, onDelete: 'CASCADE')]
    private ?Order $order = null;

    #[ORM\Column(nullable: true)]
    private ?int $productId = null;

    #[ORM\Column(length: 255)]
    private string $productName = '';

    #[ORM\Column(length: 64)]
    private string $productSku = '';

    #[ORM\Column(type: 'decimal', precision: 20, scale: 6)]
    private string $unitPrice = '0.000000';

    #[ORM\Column]
    private int $quantity = 1;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $modifiers = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'decimal', precision: 20, scale: 6)]
    private string $itemTotal = '0.000000';

    public function getId(): ?int { return $this->id; }

    public function getOrder(): ?Order { return $this->order; }
    public function setOrder(?Order $v): static { $this->order = $v; return $this; }

    public function getProductId(): ?int { return $this->productId; }
    public function setProductId(?int $v): static { $this->productId = $v; return $this; }

    public function getProductName(): string { return $this->productName; }
    public function setProductName(string $v): static { $this->productName = $v; return $this; }

    public function getProductSku(): string { return $this->productSku; }
    public function setProductSku(string $v): static { $this->productSku = $v; return $this; }

    public function getUnitPrice(): string { return $this->unitPrice; }
    public function setUnitPrice(string|float $v): static { $this->unitPrice = (string) $v; return $this; }

    public function getQuantity(): int { return $this->quantity; }
    public function setQuantity(int $v): static { $this->quantity = $v; return $this; }

    public function getModifiers(): ?array { return $this->modifiers; }
    public function setModifiers(?array $v): static { $this->modifiers = $v; return $this; }

    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $v): static { $this->notes = $v; return $this; }

    public function getItemTotal(): string { return $this->itemTotal; }
    public function setItemTotal(string|float $v): static { $this->itemTotal = (string) $v; return $this; }

    public function toArray(): array
    {
        return [
            'id'          => $this->id,
            'productId'   => $this->productId,
            'productName' => $this->productName,
            'productSku'  => $this->productSku,
            'unitPrice'   => (float) $this->unitPrice,
            'quantity'    => $this->quantity,
            'modifiers'   => $this->modifiers ?? [],
            'notes'       => $this->notes,
            'itemTotal'   => (float) $this->itemTotal,
        ];
    }
}
