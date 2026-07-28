<?php

namespace App\Module\Core\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection;
use App\Module\Core\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use App\Misc\Traits\EntityDateTimeAbleTrait;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use App\Misc\Attribute\MediaProperty;
use App\Module\Core\Enum\UserStatus;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(name: '`user`')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    use EntityDateTimeAbleTrait;
    
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $email = null;

    #[ORM\Column(length: 255)]
    private ?string $password = null;

    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column(type: "string", length: 1, enumType: UserStatus::class)]
    private UserStatus $status;

    #[ORM\Column(length: 255)]
    private ?string $firstName = null;

    #[ORM\Column(length: 255)]
    private ?string $lastName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $title = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?UserGroup $userGroup = null;

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    #[MediaProperty]
    private ?Media $logo = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $lastPing = null;

    #[ORM\Column(nullable: true)]
    private ?array $lastViewModule = null;

    #[ORM\Column(nullable: true)]
    private ?array $tableConfig = null;

    #[ORM\Column(length: 10, options: ['default' => 'en'])]
    private string $language = 'en';

    #[ORM\ManyToMany(targetEntity: Branch::class)]
    private Collection $branches;

    #[ORM\ManyToMany(targetEntity: Department::class)]
    private Collection $departments;

    public function __construct()
    {
        $this->branches = new ArrayCollection();
        $this->departments = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }
    
    public function getUserIdentifier(): string
    {
        return $this->email;
    }
    
    public function eraseCredentials(): void
    {
        // if you had a plainPassword property, you'd nullify it here
        // $this->plainPassword = null;
    }

    public function getStatus(): UserStatus
    {
        return $this->status;
    }

    public function setStatus(UserStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getFullName(): ?string
    {
        return trim(implode(' ', [$this->firstName, $this->lastName]));
    }
    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): self
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): self
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getUserGroup(): ?UserGroup
    {
        return $this->userGroup;
    }

    public function setUserGroup(?UserGroup $userGroup): static
    {
        $this->userGroup = $userGroup;

        return $this;
    }

    public function getLogo(): ?Media
    {
        return $this->logo;
    }

    public function setLogo(?Media $logo): static
    {
        $this->logo = $logo;

        return $this;
    }

    public function getLastPing(): ?\DateTimeInterface
    {
        return $this->lastPing;
    }

    public function setLastPing(?\DateTimeInterface $lastPing): static
    {
        $this->lastPing = $lastPing;

        return $this;
    }

    public function getLastViewModule(): ?array
    {
        return $this->lastViewModule;
    }

    public function setLastViewModule(?array $lastViewModule): static
    {
        $this->lastViewModule = $lastViewModule;

        return $this;
    }

    public function getTableConfig(): ?array
    {
        return $this->tableConfig;
    }

    public function setTableConfig(?array $tableConfig): static
    {
        $this->tableConfig = $tableConfig;

        return $this;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function setLanguage(string $language): static
    {
        $this->language = $language;
        return $this;
    }

    /**
     * @return Collection<int, Branch>
     */
    public function getBranches(): Collection
    {
        return $this->branches;
    }

    public function addBranch(Branch $branch): static
    {
        if (!$this->branches->contains($branch)) {
            $this->branches->add($branch);
        }

        return $this;
    }

    public function removeBranch(Branch $branch): static
    {
        $this->branches->removeElement($branch);

        return $this;
    }

    /**
     * Backward-compatible accessor that returns the first branch if any.
     */
    public function getBranch(): ?Branch
    {
        return $this->branches->first() ?: null;
    }

    /**
     * Backward-compatible mutator that replaces the branch collection with one item.
     */
    public function setBranch(?Branch $branch): static
    {
        $this->branches = new ArrayCollection();
        if ($branch) {
            $this->branches->add($branch);
        }

        return $this;
    }

    /**
     * @return Collection<int, Department>
     */
    public function getDepartments(): Collection
    {
        return $this->departments;
    }

    public function addDepartment(Department $department): static
    {
        if (!$this->departments->contains($department)) {
            $this->departments->add($department);
        }

        return $this;
    }

    public function removeDepartment(Department $department): static
    {
        $this->departments->removeElement($department);

        return $this;
    }

    /**
     * Backward-compatible accessor that returns the first department if any.
     */
    public function getDepartment(): ?Department
    {
        return $this->departments->first() ?: null;
    }

    /**
     * Backward-compatible mutator that replaces the department collection with one item.
     */
    public function setDepartment(?Department $department): static
    {
        $this->departments = new ArrayCollection();
        if ($department) {
            $this->departments->add($department);
        }

        return $this;
    }

}
