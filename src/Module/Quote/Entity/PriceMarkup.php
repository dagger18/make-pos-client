<?php

namespace App\Module\Quote\Entity;

use App\Module\Quote\Repository\PriceMarkupRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PriceMarkupRepository::class)]
class PriceMarkup
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $rules = [];

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getRules(): ?array
    {
        return $this->rules ?? [];
    }

    public function setRules(?array $rules): static
    {
        $this->rules = $rules ?? [];

        return $this;
    }
}
