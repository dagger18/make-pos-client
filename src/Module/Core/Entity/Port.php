<?php

namespace App\Module\Core\Entity;

use App\Module\Core\Enum\PortType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Module\Core\Repository\PortRepository;

#[ORM\Entity(repositoryClass: PortRepository::class)]
class Port
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $code = null;

    #[ORM\Column(type: Types::SIMPLE_ARRAY, nullable: true, enumType: PortType::class)]
    private array $portTypes = [];

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $location = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $codeFull = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $Subdiv = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): static
    {
        $this->id = $id;
        return $this;
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

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getPortTypes(): array
    {
        return $this->portTypes;
    }

    public function setPortTypes(?array $portTypes): static
    {
        $this->portTypes = $portTypes;

        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): static
    {
        $this->location = $location;

        return $this;
    }

    public function getCodeFull(): ?string
    {
        return $this->codeFull;
    }

    public function setCodeFull(?string $codeFull): static
    {
        $this->codeFull = $codeFull;

        return $this;
    }

    public function getSubdiv(): ?string
    {
        return $this->Subdiv;
    }

    public function setSubdiv(?string $Subdiv): static
    {
        $this->Subdiv = $Subdiv;

        return $this;
    }

    public function getDisplayName() {
        return $this->name . ', ' . $this->Subdiv . ' (' . $this->code . ')';
    }
}
