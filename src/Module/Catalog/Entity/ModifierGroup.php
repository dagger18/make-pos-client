<?php

namespace App\Module\Catalog\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Module\Core\Entity\Location;
use App\Module\Catalog\Repository\ModifierGroupRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: ModifierGroupRepository::class)]
#[ORM\Table(name: 'modifier_group')]
class ModifierGroup
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Location::class)]
    #[ORM\JoinColumn(name: 'location_id', nullable: false)]
    private ?Location $location = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column]
    private bool $required = false;

    #[ORM\Column]
    private int $min = 0;

    #[ORM\Column(nullable: true)]
    private ?int $max = null;

    #[ORM\Column]
    private int $position = 0;

    #[ORM\OneToMany(targetEntity: Modifier::class, mappedBy: 'group', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $modifiers;

    public function __construct()
    {
        $this->modifiers = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getLocation(): ?Location { return $this->location; }
    public function setLocation(?Location $location): static { $this->location = $location; return $this; }

    public function getName(): ?string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }

    public function isRequired(): bool { return $this->required; }
    public function setRequired(bool $required): static { $this->required = $required; return $this; }

    public function getMin(): int { return $this->min; }
    public function setMin(int $min): static { $this->min = $min; return $this; }

    public function getMax(): ?int { return $this->max; }
    public function setMax(?int $max): static { $this->max = $max; return $this; }

    public function getPosition(): int { return $this->position; }
    public function setPosition(int $position): static { $this->position = $position; return $this; }

    public function getModifiers(): Collection { return $this->modifiers; }
    public function addModifier(Modifier $modifier): static
    {
        if (!$this->modifiers->contains($modifier)) {
            $this->modifiers->add($modifier);
            $modifier->setGroup($this);
        }
        return $this;
    }
    public function removeModifier(Modifier $modifier): static
    {
        $this->modifiers->removeElement($modifier);
        return $this;
    }
}
