<?php

namespace App\Module\Quote\Entity;

use App\Module\Core\Enum\TransportType;
use App\Module\Quote\Repository\CalculationTypeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CalculationTypeRepository::class)]
class CalculationType
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: Types::SIMPLE_ARRAY, enumType: TransportType::class)]
    private array $transportTypes = [];

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $code = null;

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

    public function getTransportTypes(): array
    {
        return $this->transportTypes;
    }

    public function setTransportTypes(array $transportTypes): static
    {
        $this->transportTypes = $transportTypes;

        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): static
    {
        $this->code = $code;

        return $this;
    }
}
