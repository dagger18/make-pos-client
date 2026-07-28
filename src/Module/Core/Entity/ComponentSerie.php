<?php
namespace App\Module\Core\Entity;

class ComponentSerie
{
    private ?string $type = null;
    private ?string $color = null;
    private ?string $column = null;


    /**
     * Get the value of type
     *
     * @return ?string
     */
    public function getType(): ?string
    {
        return $this->type;
    }

    /**
     * Set the value of type
     *
     * @param ?string $type
     *
     * @return self
     */
    public function setType(?string $type): self
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Get the value of color
     *
     * @return ?string
     */
    public function getColor(): ?string
    {
        return $this->color;
    }

    /**
     * Set the value of color
     *
     * @param ?string $color
     *
     * @return self
     */
    public function setColor(?string $color): self
    {
        $this->color = $color;

        return $this;
    }

    /**
     * Get the value of column
     *
     * @return ?string
     */
    public function getColumn(): ?string
    {
        return $this->column;
    }

    /**
     * Set the value of column
     *
     * @param ?string $column
     *
     * @return self
     */
    public function setColumn(?string $column): self
    {
        $this->column = $column;

        return $this;
    }
}