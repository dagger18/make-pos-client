<?php

namespace App\Module\Reporting\Entity;

use App\Module\Core\Entity\User;

use App\Module\Core\Entity\Component;

use App\Module\Core\Enum\EntityType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Module\Reporting\Repository\DatasetRepository;
use Doctrine\Common\Collections\Collection;
use App\Misc\Traits\EntityDateTimeAbleTrait;
use Doctrine\Common\Collections\ArrayCollection;
use App\Misc\Doctrine\Collection\DatasetPropCollection;
use App\Misc\Doctrine\Collection\DatasetFilterCollection;

#[ORM\Entity(repositoryClass: DatasetRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Dataset
{
    use EntityDateTimeAbleTrait;
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255, enumType: EntityType::class)]
    private ?EntityType $entityType;

    #[ORM\Column(nullable: true, type: 'DatasetFilterCollectionType')]
    private DatasetFilterCollection $filters;

    #[ORM\Column(type: Types::SIMPLE_ARRAY, nullable: true)]
    private array $columns = [];

    #[ORM\Column(type: Types::SIMPLE_ARRAY, nullable: true)]
    private array $stats = [];

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $createdBy = null;

    #[ORM\Column(length: 255)]
    private ?string $rowType = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $groupColumn = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $dateSegment = null;

    #[ORM\Column(nullable: true)]
    private ?int $orderNumber = null;

    #[ORM\OneToMany(mappedBy: 'dataset', targetEntity: Component::class, orphanRemoval: true, cascade: ["all"])]
    private Collection $components;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $currency = null;

    public function __construct()
    {
        $this->filters = new DatasetFilterCollection();
        $this->components = new ArrayCollection();
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

    public function getEntityType(): ?EntityType
    {
        return $this->entityType;
    }

    public function setEntityType(EntityType $entityType): static
    {
        $this->entityType = $entityType;

        return $this;
    }

    public function getFilters(): ?DatasetFilterCollection
    {
        return $this->filters;
    }

    public function addFilter(DatasetFilter $filter): static
    { 
        if(count($this->filters) === 0) {
            $this->filters = new DatasetFilterCollection();
        }
        $this->filters[] = $filter;
        $this->filters = clone $this->filters;
        // timecapsule : https://github.com/doctrine/orm/issues/5542#issuecomment-1418301080
        return $this;
    }

    public function removeFilter(DatasetFilter $filter): static
    {
        forEach($this->filters as $index=>$q) {
            if($q === $filter ) {
                unset($this->filters[$index]);
            }
        }

        return $this;
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

    /**
     * Get the value of columns
     *
     * @return array
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * Set the value of columns
     *
     * @param array $columns
     *
     * @return self
     */
    public function setColumns(?array $columns): self
    {
        $this->columns = $columns;

        return $this;
    }

    /**
     * Get the value of stats
     *
     * @return array
     */
    public function getStats(): array
    {
        return $this->stats;
    }

    /**
     * Set the value of stats
     *
     * @param array $stats
     *
     * @return self
     */
    public function setStats(?array $stats): self
    {
        $this->stats = $stats;

        return $this;
    }

    public function getRowType(): ?string
    {
        return $this->rowType;
    }

    public function setRowType(string $rowType): static
    {
        $this->rowType = $rowType;

        return $this;
    }

    public function getGroupColumn(): ?string
    {
        return $this->groupColumn;
    }

    public function setGroupColumn(?string $groupColumn): static
    {
        $this->groupColumn = $groupColumn;

        return $this;
    }

    public function getDateSegment(): ?string
    {
        return $this->dateSegment;
    }

    public function setDateSegment(?string $dateSegment): static
    {
        $this->dateSegment = $dateSegment;

        return $this;
    }

    public function getOrderNumber(): ?int
    {
        return $this->orderNumber;
    }

    public function setOrderNumber(?int $orderNumber): static
    {
        $this->orderNumber = $orderNumber;

        return $this;
    }

    /**
     * @return Collection<int, Component>
     */
    public function getComponents(): Collection
    {
        return $this->components;
    }

    public function addComponent(Component $component): static
    {
        if (!$this->components->contains($component)) {
            $this->components->add($component);
            $component->setDataset($this);
        }

        return $this;
    }

    public function removeComponent(Component $component): static
    {
        if ($this->components->removeElement($component)) {
            // set the owning side to null (unless already changed)
            if ($component->getDataset() === $this) {
                $component->setDataset(null);
            }
        }

        return $this;
    }

    public function getCurrency(): ?String
    {
        return $this->currency;
    }

    public function setCurrency(?String $currency): static
    {
        $this->currency = $currency;

        return $this;
    }
}
