<?php

namespace App\Module\Core\Entity;

use App\Module\Core\Enum\Permission;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Module\Core\Repository\UserGroupRepository;

#[ORM\Entity(repositoryClass: UserGroupRepository::class)]
class UserGroup
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::SIMPLE_ARRAY, nullable: true)]
    private array $permissions = [];

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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    /** @return Permission[] */
    public function getPermissions(): array
    {
        return array_values(array_filter(array_map(function ($p) {
            if ($p instanceof Permission) return $p;
            try { return Permission::from((string) $p); } catch (\ValueError) { return null; }
        }, $this->permissions)));
    }

    /** @param Permission[]|string[] $permissions */
    public function setPermissions(?array $permissions): static
    {
        $this->permissions = array_map(
            fn($p) => $p instanceof Permission ? $p->value : (string) $p,
            $permissions ?? []
        );

        return $this;
    }
}
