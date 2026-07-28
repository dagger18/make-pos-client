<?php

namespace App\Module\Finance\Entity;

use App\Module\Finance\Repository\CurrencyRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CurrencyRepository::class)]
class Currency
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $symbol = null;

    #[ORM\Column(length: 255)]
    private ?string $code = null;

    #[ORM\Column(nullable: true)]
    private ?float $rate = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $thousandSeparator = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $decimalSeparator = null;

    #[ORM\Column(nullable: true)]
    private ?int $decimalPlaces = null;

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

    public function getSymbol(): ?string
    {
        return $this->symbol;
    }

    public function setSymbol(string $symbol): static
    {
        $this->symbol = $symbol;

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

    public function getRate(): ?float
    {
        return $this->rate;
    }

    public function setRate(?float $rate): static
    {
        $this->rate = $rate;

        return $this;
    }

    public function getThousandSeparator(): ?string { return $this->thousandSeparator; }
    public function setThousandSeparator(?string $thousandSeparator): static { $this->thousandSeparator = $thousandSeparator; return $this; }

    public function getDecimalSeparator(): ?string { return $this->decimalSeparator; }
    public function setDecimalSeparator(?string $decimalSeparator): static { $this->decimalSeparator = $decimalSeparator; return $this; }

    public function getDecimalPlaces(): ?int { return $this->decimalPlaces; }
    public function setDecimalPlaces(?int $decimalPlaces): static { $this->decimalPlaces = $decimalPlaces; return $this; }
}
