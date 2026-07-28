<?php
namespace App\Module\Crm\Entity;

use App\Module\Carrier\Entity\Provider;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Module\Crm\Repository\AgentProfileRepository;

#[ORM\Entity(repositoryClass: AgentProfileRepository::class)]
class AgentProfile
{
    #[ORM\Id]
    #[ORM\OneToOne(inversedBy: 'agentProfile')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Provider $provider = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $network = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $agentCode = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $coverageCountries = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $modesHandled = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 6, scale: 4, nullable: true)]
    private ?string $commissionRate = null;

    #[ORM\Column(length: 3, nullable: true)]
    private ?string $settlementCurrency = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $settlementTerms = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 4, scale: 2, nullable: true)]
    private ?string $performanceScore = null;

    public function getProvider(): ?Provider { return $this->provider; }
    public function setProvider(Provider $provider): static { $this->provider = $provider; return $this; }

    public function getNetwork(): ?string { return $this->network; }
    public function setNetwork(?string $network): static { $this->network = $network; return $this; }

    public function getAgentCode(): ?string { return $this->agentCode; }
    public function setAgentCode(?string $agentCode): static { $this->agentCode = $agentCode; return $this; }

    public function getCoverageCountries(): ?array { return $this->coverageCountries; }
    public function setCoverageCountries(?array $coverageCountries): static { $this->coverageCountries = $coverageCountries; return $this; }

    public function getModesHandled(): ?array { return $this->modesHandled; }
    public function setModesHandled(?array $modesHandled): static { $this->modesHandled = $modesHandled; return $this; }

    public function getCommissionRate(): ?string { return $this->commissionRate; }
    public function setCommissionRate(?string $commissionRate): static { $this->commissionRate = $commissionRate; return $this; }

    public function getSettlementCurrency(): ?string { return $this->settlementCurrency; }
    public function setSettlementCurrency(?string $settlementCurrency): static { $this->settlementCurrency = $settlementCurrency; return $this; }

    public function getSettlementTerms(): ?string { return $this->settlementTerms; }
    public function setSettlementTerms(?string $settlementTerms): static { $this->settlementTerms = $settlementTerms; return $this; }

    public function getPerformanceScore(): ?string { return $this->performanceScore; }
    public function setPerformanceScore(?string $performanceScore): static { $this->performanceScore = $performanceScore; return $this; }
}
