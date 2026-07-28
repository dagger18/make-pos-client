<?php

namespace App\Module\Finance\Entity;

use App\Module\Finance\Repository\ExchangeRateRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ExchangeRateRepository::class)]
class ExchangeRate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?float $fromRate = null;

    #[ORM\Column]
    private ?float $toRate = null;

    #[ORM\Column]
    private ?float $rate = null;

    #[ORM\ManyToOne(inversedBy: 'exchangeRates')]
    #[ORM\JoinColumn(nullable: false)]
    private ?ExchangeRateGroup $exchangeRateGroup = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Currency $fromCurrency = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Currency $toCurrency = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFromRate(): ?float
    {
        return $this->fromRate;
    }

    public function setFromRate(float $fromRate): static
    {
        $this->fromRate = $fromRate;

        return $this;
    }

    public function getToRate(): ?float
    {
        return $this->toRate;
    }

    public function setToRate(float $toRate): static
    {
        $this->toRate = $toRate;

        return $this;
    }

    public function getRate(): ?float
    {
        return $this->rate;
    }

    public function setRate(float $rate): static
    {
        $this->rate = $rate;

        return $this;
    }

    public function getExchangeRateGroup(): ?ExchangeRateGroup
    {
        return $this->exchangeRateGroup;
    }

    public function setExchangeRateGroup(?ExchangeRateGroup $exchangeRateGroup): static
    {
        $this->exchangeRateGroup = $exchangeRateGroup;

        return $this;
    }

    public function getFromCurrency(): ?Currency
    {
        return $this->fromCurrency;
    }

    public function setFromCurrency(?Currency $fromCurrency): static
    {
        $this->fromCurrency = $fromCurrency;

        return $this;
    }

    public function getToCurrency(): ?Currency
    {
        return $this->toCurrency;
    }

    public function setToCurrency(?Currency $toCurrency): static
    {
        $this->toCurrency = $toCurrency;

        return $this;
    }
}
