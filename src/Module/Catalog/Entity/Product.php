<?php

namespace App\Module\Catalog\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Module\Core\Entity\Location;
use App\Module\Core\Entity\Media;
use App\Module\Catalog\Enum\ProductType;
use App\Module\Catalog\Repository\ProductRepository;
use App\Misc\Traits\EntityDateTimeAbleTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ORM\Table(name: 'product')]
#[ORM\UniqueConstraint(name: 'uq_product_sku_location', columns: ['sku', 'location_id'])]
#[ORM\HasLifecycleCallbacks]
class Product
{
    use EntityDateTimeAbleTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Location::class)]
    #[ORM\JoinColumn(name: 'location_id', nullable: false)]
    private ?Location $location = null;

    #[ORM\ManyToOne(targetEntity: ProductCategory::class)]
    #[ORM\JoinColumn(name: 'category_id', nullable: true, onDelete: 'SET NULL')]
    private ?ProductCategory $category = null;

    #[ORM\ManyToOne(targetEntity: Media::class)]
    #[ORM\JoinColumn(name: 'image_id', nullable: true, onDelete: 'SET NULL')]
    private ?Media $image = null;

    #[ORM\Column(length: 64)]
    private ?string $sku = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'decimal', precision: 20, scale: 6)]
    private string $price = '0.000000';

    #[ORM\Column(type: 'decimal', precision: 20, scale: 6, nullable: true)]
    private ?string $cost = null;

    #[ORM\Column(length: 8, enumType: ProductType::class)]
    private ProductType $type = ProductType::Retail;

    #[ORM\Column]
    private bool $active = true;

    #[ORM\ManyToMany(targetEntity: 'ModifierGroup')]
    #[ORM\JoinTable(name: 'product_modifier_group')]
    private Collection $modifierGroups;

    #[ORM\OneToMany(targetEntity: 'ProductModifier', mappedBy: 'product', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $modifiers;

    public function __construct()
    {
        $this->modifierGroups = new ArrayCollection();
        $this->modifiers      = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getLocation(): ?Location { return $this->location; }
    public function setLocation(?Location $location): static { $this->location = $location; return $this; }

    public function getCategory(): ?ProductCategory { return $this->category; }
    public function setCategory(?ProductCategory $category): static { $this->category = $category; return $this; }

    public function getImage(): ?Media { return $this->image; }
    public function setImage(?Media $image): static { $this->image = $image; return $this; }

    public function getSku(): ?string { return $this->sku; }
    public function setSku(string $sku): static { $this->sku = $sku; return $this; }

    public function getName(): ?string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }

    public function getPrice(): string { return $this->price; }
    public function setPrice(string|float $price): static { $this->price = (string) $price; return $this; }

    public function getCost(): ?string { return $this->cost; }
    public function setCost(string|float|null $cost): static { $this->cost = $cost !== null ? (string) $cost : null; return $this; }

    public function getType(): ProductType { return $this->type; }
    public function setType(ProductType $type): static { $this->type = $type; return $this; }

    public function isActive(): bool { return $this->active; }
    public function setActive(bool $active): static { $this->active = $active; return $this; }

    public function getModifierGroups(): Collection { return $this->modifierGroups; }
    public function addModifierGroup($group): static
    {
        if (!$this->modifierGroups->contains($group)) {
            $this->modifierGroups->add($group);
        }
        return $this;
    }
    public function removeModifierGroup($group): static
    {
        $this->modifierGroups->removeElement($group);
        return $this;
    }

    public function getModifiers(): Collection { return $this->modifiers; }
}
