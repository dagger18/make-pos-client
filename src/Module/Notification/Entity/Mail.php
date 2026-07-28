<?php

namespace App\Module\Notification\Entity;

use App\Module\Core\Entity\User;

use App\Module\Core\Enum\EntityType;
use App\Module\Notification\Enum\MailStatus;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Module\Notification\Repository\MailRepository;
use App\Misc\Traits\EntityDateTimeAbleTrait;

#[ORM\Entity(repositoryClass: MailRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Mail
{
    use EntityDateTimeAbleTrait;
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $createdBy = null;

    #[ORM\Column(length: 255)]
    private ?string $fromAddress = null;

    #[ORM\Column(length: 255)]
    private ?string $toAddress = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(length: 255, enumType: MailStatus::class)]
    private ?MailStatus $status;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $log = null;

    #[ORM\Column(length: 255, nullable: true, enumType: EntityType::class)]
    private ?EntityType $parentType = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $parentId = null;

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

    public function getFromAddress(): ?string
    {
        return $this->fromAddress;
    }

    public function setFromAddress(string $fromAddress): static
    {
        $this->fromAddress = $fromAddress;

        return $this;
    }

    public function getToAddress(): ?string
    {
        return $this->toAddress;
    }

    public function setToAddress(string $toAddress): static
    {
        $this->toAddress = $toAddress;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getStatus(): ?MailStatus
    {
        return $this->status;
    }

    public function setStatus(MailStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getLog(): ?string
    {
        return $this->log;
    }

    public function setLog(?string $log): static
    {
        $this->log = $log;

        return $this;
    }

    public function getParentType(): ?EntityType
    {
        return $this->parentType;
    }

    public function setParentType(?EntityType $parentType): static
    {
        $this->parentType = $parentType;

        return $this;
    }

    public function getParentId(): ?string
    {
        return $this->parentId;
    }

    public function setParentId(?string $parentId): static
    {
        $this->parentId = $parentId;

        return $this;
    }
}
