<?php
namespace App\Module\Tax\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Module\Tax\Repository\HsVersionMappingRepository;
use App\Misc\Traits\EntityDateTimeAbleTrait;

#[ORM\Entity(repositoryClass: HsVersionMappingRepository::class)]
#[ORM\HasLifecycleCallbacks]
class HsVersionMapping
{
    use EntityDateTimeAbleTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: HsCode::class)]
    #[ORM\JoinColumn(name: 'old_hs_code_id', nullable: false, onDelete: 'CASCADE')]
    private ?HsCode $oldHsCode = null;

    #[ORM\ManyToOne(targetEntity: HsCode::class)]
    #[ORM\JoinColumn(name: 'new_hs_code_id', nullable: false, onDelete: 'CASCADE')]
    private ?HsCode $newHsCode = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $oldVersion = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $newVersion = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $changeType = null;

    public function getId(): ?int { return $this->id; }

    public function getOldHsCode(): ?HsCode { return $this->oldHsCode; }
    public function setOldHsCode(?HsCode $oldHsCode): static { $this->oldHsCode = $oldHsCode; return $this; }

    public function getNewHsCode(): ?HsCode { return $this->newHsCode; }
    public function setNewHsCode(?HsCode $newHsCode): static { $this->newHsCode = $newHsCode; return $this; }

    public function getOldVersion(): ?string { return $this->oldVersion; }
    public function setOldVersion(?string $oldVersion): static { $this->oldVersion = $oldVersion; return $this; }

    public function getNewVersion(): ?string { return $this->newVersion; }
    public function setNewVersion(?string $newVersion): static { $this->newVersion = $newVersion; return $this; }

    public function getChangeType(): ?string { return $this->changeType; }
    public function setChangeType(?string $changeType): static { $this->changeType = $changeType; return $this; }
}
