<?php

namespace App\Module\Core\Entity;

use App\Module\Reporting\Entity\Dataset;

use App\Module\Core\Enum\ComponentType;
use Doctrine\ORM\Mapping as ORM;
use App\Module\Core\Repository\ComponentRepository;
use App\Misc\Doctrine\Collection\ComponentSerieCollection;

#[ORM\Entity(repositoryClass: ComponentRepository::class)]
class Component
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'components')]
    #[ORM\JoinColumn(onDelete: "CASCADE")]
    private ?Dataset $dataset = null;

    #[ORM\Column(nullable: true)]
    private ?array $filters = null;

    #[ORM\Column]
    private ?int $rowNumber = null;

    #[ORM\Column]
    private ?int $orderNumber = null;

    #[ORM\Column]
    private ?int $columnWidth = null;

    #[ORM\Column(length: 255, enumType: ComponentType::class)]
    private ?ComponentType $type;

    #[ORM\ManyToOne(inversedBy: 'components')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Page $page = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(nullable: true, type: 'ComponentSerieCollectionType')]
    private ?ComponentSerieCollection $series;

    #[ORM\Column(nullable: true)]
    private ?bool $stacked = null;

    public function __construct()
    {
        $this->series = new ComponentSerieCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDataset(): ?Dataset
    {
        return $this->dataset;
    }

    public function setDataset(?Dataset $dataset): static
    {
        $this->dataset = $dataset;

        return $this;
    }

    public function getFilters(): ?array
    {
        return $this->filters;
    }

    public function setFilters(?array $filters): static
    {
        $this->filters = $filters;

        return $this;
    }

    public function getRowNumber(): ?int
    {
        return $this->rowNumber;
    }

    public function setRowNumber(int $rowNumber): static
    {
        $this->rowNumber = $rowNumber;

        return $this;
    }

    public function getOrderNumber(): ?int
    {
        return $this->orderNumber;
    }

    public function setOrderNumber(int $orderNumber): static
    {
        $this->orderNumber = $orderNumber;

        return $this;
    }

    public function getColumnWidth(): ?int
    {
        return $this->columnWidth;
    }

    public function setColumnWidth(int $columnWidth): static
    {
        $this->columnWidth = $columnWidth;

        return $this;
    }

    public function getType(): ?ComponentType
    {
        return $this->type;
    }

    public function setType(ComponentType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getPage(): ?Page
    {
        return $this->page;
    }

    public function setPage(?Page $page): static
    {
        $this->page = $page;

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
    // note for 3 hours wasted here
    // check for plural / singular add / remove method, it might not be addSerie 
    // series is a zero word, which is uncountable, its singular form is also series
    public function getSeries(): ?ComponentSerieCollection
    {
        return $this->series;
    }

    public function addSeries(ComponentSerie $serie): static
    { 
        if(is_null($this->series) || count($this->series) === 0) {
            $this->series = new ComponentSerieCollection();
        }
        $this->series[] = $serie;
        $this->series = clone $this->series;
        // timecapsule : https://github.com/doctrine/orm/issues/5542#issuecomment-1418301080
        return $this;
    }

    public function removeSeries(ComponentSerie $serie): static
    {
        forEach($this->series as $index=>$q) {
            if($q === $serie ) {
                unset($this->series[$index]);
            }
        }

        return $this;
    }

    public function isStacked(): ?bool
    {
        return $this->stacked;
    }

    public function setStacked(?bool $stacked): static
    {
        $this->stacked = $stacked;

        return $this;
    }

}
