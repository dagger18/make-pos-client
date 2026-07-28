<?php
namespace App\Module\Inventory\Entity;

use App\Module\Catalog\Entity\Product;
use App\Module\Core\Entity\Location;
use App\Module\Inventory\Repository\StockLevelRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StockLevelRepository::class)]
#[ORM\Table(name: 'stock_level')]
#[ORM\UniqueConstraint(name: 'uq_stock_level_location_product', columns: ['location_id', 'product_id'])]
class StockLevel
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Location::class)]
    #[ORM\JoinColumn(name: 'location_id', nullable: false)]
    private ?Location $location = null;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(name: 'product_id', nullable: true, onDelete: 'SET NULL')]
    private ?Product $product = null;

    #[ORM\Column(length: 255)]
    private string $productName = '';

    #[ORM\Column(length: 64)]
    private string $productSku = '';

    #[ORM\Column]
    private int $quantity = 0;

    #[ORM\Column(nullable: true)]
    private ?int $lowStockThreshold = null;

    #[ORM\OneToMany(targetEntity: StockMovement::class, mappedBy: 'stockLevel', cascade: ['remove'], orphanRemoval: true)]
    private Collection $movements;

    public function __construct()
    {
        $this->movements = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getLocation(): ?Location { return $this->location; }
    public function setLocation(?Location $v): static { $this->location = $v; return $this; }

    public function getProduct(): ?Product { return $this->product; }
    public function setProduct(?Product $v): static { $this->product = $v; return $this; }

    public function getProductName(): string { return $this->productName; }
    public function setProductName(string $v): static { $this->productName = $v; return $this; }

    public function getProductSku(): string { return $this->productSku; }
    public function setProductSku(string $v): static { $this->productSku = $v; return $this; }

    public function getQuantity(): int { return $this->quantity; }
    public function setQuantity(int $v): static { $this->quantity = $v; return $this; }

    public function getLowStockThreshold(): ?int { return $this->lowStockThreshold; }
    public function setLowStockThreshold(?int $v): static { $this->lowStockThreshold = $v; return $this; }

    public function getMovements(): Collection { return $this->movements; }

    public function isLowStock(): bool
    {
        return $this->lowStockThreshold !== null && $this->quantity <= $this->lowStockThreshold;
    }

    public function toArray(): array
    {
        return [
            'id'                => $this->id,
            'productId'         => $this->product?->getId(),
            'productName'       => $this->productName,
            'productSku'        => $this->productSku,
            'quantity'          => $this->quantity,
            'lowStockThreshold' => $this->lowStockThreshold,
            'isLowStock'        => $this->isLowStock(),
        ];
    }
}
