<?php
namespace App\Module\Core\Entity;

use Doctrine\ORM\Mapping\Embeddable;
use Doctrine\ORM\Mapping as ORM;

#[Embeddable]
class Money 
{
    #[ORM\Column(type: "decimal", precision: 15, scale: 4, nullable: true)]
    private ?float $amount;

    #[ORM\Column(type: "string", nullable: true)]
    private ?string $currency;

    #[ORM\Column(type: "decimal", precision: 15, scale: 6, nullable: true)]
    private ?float $rate;

    public function __construct(?float $amount, ?string $currency, ?float $rate) 
    {
        $this->amount = $amount;
        $this->currency = $currency;
        $this->rate = $rate;
    }

    public function getAmount(): ?float 
    {
        return $this->amount;
    }

    public function getCurrency(): ?string 
    {
        return $this->currency;
    }

    public function getRate(): ?float 
    {
        return $this->rate;
    }
}