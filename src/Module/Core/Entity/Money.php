<?php
namespace App\Module\Core\Entity;

use Doctrine\ORM\Mapping\Embeddable;
use Doctrine\ORM\Mapping as ORM;

#[Embeddable]
class Money 
{
    #[ORM\Column(type: "decimal", precision: 15, scale: 4, nullable: true)]
    private ?string $amount;

    #[ORM\Column(type: "string", nullable: true)]
    private ?string $currency;

    #[ORM\Column(type: "decimal", precision: 15, scale: 6, nullable: true)]
    private ?string $rate;

    public function __construct(?float $amount, ?string $currency, ?float $rate)
    {
        $this->amount = $amount !== null ? (string) $amount : null;
        $this->currency = $currency;
        $this->rate = $rate !== null ? (string) $rate : null;
    }

    public function getAmount(): ?float
    {
        return $this->amount !== null ? (float) $this->amount : null;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function getRate(): ?float
    {
        return $this->rate !== null ? (float) $this->rate : null;
    }
}