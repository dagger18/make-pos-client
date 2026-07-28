<?php
namespace App\Module\Carrier\Entity;

use App\Module\Carrier\Enum\MilestoneCode;
use App\Misc\Traits\EntityDateTimeAbleTrait;
use App\Module\Carrier\Repository\CarrierEventMappingRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CarrierEventMappingRepository::class)]
#[ORM\UniqueConstraint(name: 'UQ_carrier_event', columns: ['carrier_scac', 'carrier_event_code'])]
#[ORM\HasLifecycleCallbacks]
class CarrierEventMapping
{
    use EntityDateTimeAbleTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 8)]
    private string $carrierScac;

    #[ORM\Column(length: 64)]
    private string $carrierEventCode;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $carrierEventDescription = null;

    #[ORM\Column(length: 32, enumType: MilestoneCode::class, nullable: true)]
    private ?MilestoneCode $milestoneCode = null;

    #[ORM\Column(length: 8)]
    private string $confidence = 'HIGH';

    public function getId(): ?int { return $this->id; }
    public function getCarrierScac(): string { return $this->carrierScac; }
    public function setCarrierScac(string $v): static { $this->carrierScac = $v; return $this; }
    public function getCarrierEventCode(): string { return $this->carrierEventCode; }
    public function setCarrierEventCode(string $v): static { $this->carrierEventCode = $v; return $this; }
    public function getCarrierEventDescription(): ?string { return $this->carrierEventDescription; }
    public function setCarrierEventDescription(?string $v): static { $this->carrierEventDescription = $v; return $this; }
    public function getMilestoneCode(): ?MilestoneCode { return $this->milestoneCode; }
    public function setMilestoneCode(?MilestoneCode $v): static { $this->milestoneCode = $v; return $this; }
    public function getConfidence(): string { return $this->confidence; }
    public function setConfidence(string $v): static { $this->confidence = $v; return $this; }
}
