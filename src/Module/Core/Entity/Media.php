<?php

namespace App\Module\Core\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Module\Core\Enum\EntityType;
use App\Module\Core\Enum\MediaCategory;
use App\Module\Core\Repository\MediaRepository;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Mapping\ClassMetadata;

#[ORM\Entity(repositoryClass: MediaRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Media implements SubEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 5)]
    private ?string $type = null;

    #[ORM\Column(length: 255)]
    private ?string $path = null;

    #[ORM\Column]
    private ?int $size = null;

    #[ORM\Column(nullable: true)]
    private ?bool $hasWebp = null;

    #[ORM\Column(nullable: true)]
    private ?bool $hasThumb = null;

    #[ORM\Column(nullable: true)]
    private ?int $createdBy = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdDate = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $duration = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $width = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $height = null;

    #[ORM\Column(length: 255, nullable: true, enumType: EntityType::class)]
    private ?EntityType $parentType = null;

    #[ORM\Column(nullable: true)]
    private ?int $parentId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $parentProperty = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $language = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $note = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $mime = null;

    #[ORM\Column(length: 255, nullable: true, enumType: MediaCategory::class)]
    private ?MediaCategory $category = null;

    #[ORM\Column(nullable: true)]
    private ?bool $s3Success = null;

    #[ORM\Column(nullable: true)]
    private ?bool $backupSuccess = null;

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        if (!$this->createdDate) {
            $this->createdDate = new \DateTime('now');
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(string $path): static
    {
        $this->path = $path;

        return $this;
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function setSize(int $size): static
    {
        $this->size = $size;

        return $this;
    }

    public function isHasWebp(): ?bool
    {
        return $this->hasWebp;
    }

    public function setHasWebp(?bool $hasWebp): static
    {
        $this->hasWebp = $hasWebp;

        return $this;
    }

    public function isHasThumb(): ?bool
    {
        return $this->hasThumb;
    }

    public function setHasThumb(?bool $hasThumb): static
    {
        $this->hasThumb = $hasThumb;

        return $this;
    }

    public function getCreatedBy(): ?int
    {
        return $this->createdBy;
    }

    public function setCreatedBy(null|int|User $createdBy): static
    {
        if($createdBy instanceof User) {
            $this->createdBy = $createdBy->getId();
        } else {
            $this->createdBy = $createdBy;
        }

        return $this;
    }

    public function getCreatedDate(): ?\DateTimeInterface
    {
        return $this->createdDate;
    }

    public function setCreatedDate(\DateTimeInterface $createdDate): static
    {
        $this->createdDate = $createdDate;

        return $this;
    }

    public function getDuration(): ?int
    {
        return $this->duration;
    }

    public function setDuration(?int $duration): static
    {
        $this->duration = $duration;

        return $this;
    }

    public function getWidth(): ?int
    {
        return $this->width;
    }

    public function setWidth(?int $width): static
    {
        $this->width = $width;

        return $this;
    }

    public function getHeight(): ?int
    {
        return $this->height;
    }

    public function setHeight(?int $height): static
    {
        $this->height = $height;

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

    public function getParentId(): ?int
    {
        return $this->parentId;
    }

    public function setParentId(?int $parentId): static
    {
        $this->parentId = $parentId;

        return $this;
    }

    public function getParentProperty(): ?string
    {
        return $this->parentProperty;
    }

    public function setParentProperty(?string $parentProperty): static
    {
        $this->parentProperty = $parentProperty;

        return $this;
    }

    public function getLanguage(): ?string
    {
        return $this->language;
    }

    public function setLanguage(?string $language): static
    {
        $this->language = $language;

        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $this->note = $note;

        return $this;
    }

    public function getMime(): ?string
    {
        return $this->mime;
    }

    public function setMime(?string $mime): static
    {
        $this->mime = $mime;

        return $this;
    }

    public function getCategory(): ?MediaCategory
    {
        return $this->category;
    }

    public function setCategory(?MediaCategory $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function isS3Success(): ?bool
    {
        return $this->s3Success;
    }

    public function setS3Success(?bool $s3Success): static
    {
        $this->s3Success = $s3Success;

        return $this;
    }

    public function isBackupSuccess(): ?bool
    {
        return $this->backupSuccess;
    }

    public function setBackupSuccess(?bool $backupSuccess): static
    {
        $this->backupSuccess = $backupSuccess;

        return $this;
    }
}
