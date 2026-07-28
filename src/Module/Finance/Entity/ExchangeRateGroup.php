<?php

namespace App\Module\Finance\Entity;

use App\Module\Core\Entity\User;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Misc\Traits\EntityDateTimeAbleTrait;
use App\Module\Finance\Repository\ExchangeRateGroupRepository;

#[ORM\Entity(repositoryClass: ExchangeRateGroupRepository::class)]
#[ORM\HasLifecycleCallbacks]
class ExchangeRateGroup
{
    use EntityDateTimeAbleTrait;
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $createdBy = null;

    #[ORM\Column(nullable: true)]
    private ?bool $isDefault = null;

    #[ORM\OneToMany(mappedBy: 'exchangeRateGroup', targetEntity: ExchangeRate::class, orphanRemoval: true, cascade: ['persist'])]
    private Collection $exchangeRates;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Currency $currency = null;

    public function __construct()
    {
        $this->exchangeRates = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function isIsDefault(): ?bool
    {
        return $this->isDefault;
    }

    public function setIsDefault(?bool $isDefault): static
    {
        $this->isDefault = $isDefault;

        return $this;
    }

    /**
     * @return Collection<int, ExchangeRate>
     */
    public function getExchangeRates(): Collection
    {
        return $this->exchangeRates;
    }

    public function addExchangeRate(ExchangeRate $exchangeRate): static
    {
        if (!$this->exchangeRates->contains($exchangeRate)) {
            $this->exchangeRates->add($exchangeRate);
            $exchangeRate->setExchangeRateGroup($this);
        }

        return $this;
    }

    public function removeExchangeRate(ExchangeRate $exchangeRate): static
    {
        if ($this->exchangeRates->removeElement($exchangeRate)) {
            // set the owning side to null (unless already changed)
            if ($exchangeRate->getExchangeRateGroup() === $this) {
                $exchangeRate->setExchangeRateGroup(null);
            }
        }

        return $this;
    }

    public function getCurrency(): ?Currency
    {
        return $this->currency;
    }

    public function setCurrency(?Currency $currency): static
    {
        $this->currency = $currency;

        return $this;
    }
}
