<?php
namespace App\Module\Quote\Entity;

use App\Module\Core\Entity\Port;
use App\Module\Carrier\Entity\Provider;

use App\Misc\Traits\EntityDateTimeAbleTrait;
use App\Module\Quote\Repository\FreeTimeAgreementRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FreeTimeAgreementRepository::class)]
#[ORM\HasLifecycleCallbacks]
class FreeTimeAgreement
{
    use EntityDateTimeAbleTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Provider $carrier = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Port $port = null;

    #[ORM\Column(length: 16)]
    private string $direction = 'IMPORT';

    #[ORM\Column(length: 8, nullable: true)]
    private ?string $containerType = null;

    #[ORM\Column(length: 16)]
    private string $freeType = 'DETENTION';

    #[ORM\Column(type: Types::SMALLINT)]
    private int $freeDays = 7;

    #[ORM\Column(type: Types::JSON)]
    private array $rateTiers = [];

    #[ORM\Column(length: 3)]
    private string $currency = 'USD';

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $effectiveFrom = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $effectiveTo = null;

    public function getId(): ?int { return $this->id; }

    public function getCarrier(): ?Provider { return $this->carrier; }
    public function setCarrier(?Provider $carrier): static { $this->carrier = $carrier; return $this; }

    public function getPort(): ?Port { return $this->port; }
    public function setPort(?Port $port): static { $this->port = $port; return $this; }

    public function getDirection(): string { return $this->direction; }
    public function setDirection(string $direction): static { $this->direction = $direction; return $this; }

    public function getContainerType(): ?string { return $this->containerType; }
    public function setContainerType(?string $containerType): static { $this->containerType = $containerType; return $this; }

    public function getFreeType(): string { return $this->freeType; }
    public function setFreeType(string $freeType): static { $this->freeType = $freeType; return $this; }

    public function getFreeDays(): int { return $this->freeDays; }
    public function setFreeDays(int $freeDays): static { $this->freeDays = $freeDays; return $this; }

    public function getRateTiers(): array { return $this->rateTiers; }
    public function setRateTiers(array $rateTiers): static { $this->rateTiers = $rateTiers; return $this; }

    public function getCurrency(): string { return $this->currency; }
    public function setCurrency(string $currency): static { $this->currency = $currency; return $this; }

    public function getEffectiveFrom(): ?\DateTimeInterface { return $this->effectiveFrom; }
    public function setEffectiveFrom(?\DateTimeInterface $effectiveFrom): static { $this->effectiveFrom = $effectiveFrom; return $this; }

    public function getEffectiveTo(): ?\DateTimeInterface { return $this->effectiveTo; }
    public function setEffectiveTo(?\DateTimeInterface $effectiveTo): static { $this->effectiveTo = $effectiveTo; return $this; }

    public function toArray(): array
    {
        return [
            'id'             => $this->id,
            'carrier'        => $this->carrier ? ['id' => $this->carrier->getId(), 'name' => $this->carrier->getName()] : null,
            'port'           => $this->port ? ['id' => $this->port->getId(), 'name' => $this->port->getName(), 'code' => $this->port->getCode()] : null,
            'direction'      => $this->direction,
            'containerType'  => $this->containerType,
            'freeType'       => $this->freeType,
            'freeDays'       => $this->freeDays,
            'rateTiers'      => $this->rateTiers,
            'currency'       => $this->currency,
            'effectiveFrom'  => $this->effectiveFrom?->format('Y-m-d'),
            'effectiveTo'    => $this->effectiveTo?->format('Y-m-d'),
            'createdDate'    => $this->createdDate?->format('Y-m-d H:i:s'),
        ];
    }
}
