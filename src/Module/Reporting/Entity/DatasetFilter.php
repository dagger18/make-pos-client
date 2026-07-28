<?php
namespace App\Module\Reporting\Entity;

class DatasetFilter
{
    private ?string $property = null;
    private ?string $operator = null;
    private mixed $value = null;

    /**
     * Get the value of operator
     *
     * @return ?string
     */
    public function getOperator(): ?string
    {
        return $this->operator;
    }

    /**
     * Set the value of operator
     *
     * @param ?string $operator
     *
     * @return self
     */
    public function setOperator(?string $operator): self
    {
        $this->operator = $operator;

        return $this;
    }

    /**
     * Get the value of value
     *
     * @return mixed
     */
    public function getValue(): mixed
    {
        return $this->value;
    }

    /**
     * Set the value of value
     *
     * @param mixed $value
     *
     * @return self
     */
    public function setValue(mixed $value): self
    {
        $this->value = $value;

        return $this;
    }

    /**
     * Get the value of property
     *
     * @return ?string
     */
    public function getProperty(): ?string
    {
        return $this->property;
    }

    /**
     * Set the value of property
     *
     * @param ?string $property
     *
     * @return self
     */
    public function setProperty(?string $property): self
    {
        $this->property = $property;

        return $this;
    }
}