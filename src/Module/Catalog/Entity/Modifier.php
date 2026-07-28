<?php

namespace App\Module\Catalog\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Module\Catalog\Repository\ModifierRepository;

#[ORM\Entity(repositoryClass: ModifierRepository::class)]
#[ORM\Table(name: 'modifier')]
class Modifier
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ModifierGroup::class, inversedBy: 'modifiers')]
    #[ORM\JoinColumn(name: 'group_id', nullable: false, onDelete: 'CASCADE')]
    private ?ModifierGroup $group = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: 'decimal', precision: 20, scale: 6)]
    private string $priceDelta = '0.000000';

    #[ORM\Column]
    private int $position = 0;

    public function getId(): ?int { return $this->id; }

    public function getGroup(): ?ModifierGroup { return $this->group; }
    public function setGroup(?ModifierGroup $group): static { $this->group = $group; return $this; }

    public function getName(): ?string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }

    public function getPriceDelta(): string { return $this->priceDelta; }
    public function setPriceDelta(string|float $priceDelta): static { $this->priceDelta = (string) $priceDelta; return $this; }

    public function getPosition(): int { return $this->position; }
    public function setPosition(int $position): static { $this->position = $position; return $this; }
}
