<?php
namespace App\Module\Carrier\Entity;

use App\Module\Carrier\Enum\CarrierType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Module\Carrier\Repository\CarrierProfileRepository;

#[ORM\Entity(repositoryClass: CarrierProfileRepository::class)]
class CarrierProfile
{
    #[ORM\Id]
    #[ORM\OneToOne(inversedBy: 'carrierProfile')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Provider $provider = null;

    #[ORM\Column(length: 8, nullable: true)]
    private ?string $scacCode = null;

    #[ORM\Column(length: 4, nullable: true)]
    private ?string $iataCode = null;

    #[ORM\Column(length: 16, nullable: true, enumType: CarrierType::class)]
    private ?CarrierType $carrierType = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $alliance = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $bookingPlatform = null;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $bookingEmail = null;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $siEmail = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $amsFiler = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $preferredPayment = null;

    public function getProvider(): ?Provider { return $this->provider; }
    public function setProvider(Provider $provider): static { $this->provider = $provider; return $this; }

    public function getScacCode(): ?string { return $this->scacCode; }
    public function setScacCode(?string $scacCode): static { $this->scacCode = $scacCode; return $this; }

    public function getIataCode(): ?string { return $this->iataCode; }
    public function setIataCode(?string $iataCode): static { $this->iataCode = $iataCode; return $this; }

    public function getCarrierType(): ?CarrierType { return $this->carrierType; }
    public function setCarrierType(?CarrierType $carrierType): static { $this->carrierType = $carrierType; return $this; }

    public function getAlliance(): ?string { return $this->alliance; }
    public function setAlliance(?string $alliance): static { $this->alliance = $alliance; return $this; }

    public function getBookingPlatform(): ?string { return $this->bookingPlatform; }
    public function setBookingPlatform(?string $bookingPlatform): static { $this->bookingPlatform = $bookingPlatform; return $this; }

    public function getBookingEmail(): ?string { return $this->bookingEmail; }
    public function setBookingEmail(?string $bookingEmail): static { $this->bookingEmail = $bookingEmail; return $this; }

    public function getSiEmail(): ?string { return $this->siEmail; }
    public function setSiEmail(?string $siEmail): static { $this->siEmail = $siEmail; return $this; }

    public function getAmsFiler(): ?string { return $this->amsFiler; }
    public function setAmsFiler(?string $amsFiler): static { $this->amsFiler = $amsFiler; return $this; }

    public function getPreferredPayment(): ?string { return $this->preferredPayment; }
    public function setPreferredPayment(?string $preferredPayment): static { $this->preferredPayment = $preferredPayment; return $this; }
}
