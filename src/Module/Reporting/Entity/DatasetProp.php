<?php
namespace App\Module\Reporting\Entity;

class DatasetProp
{
    private ?string $property = null;
    private ?string $mode = null;


    /**
     * Get the value of mode
     *
     * @return ?string
     */
    public function getMode(): ?string
    {
        return $this->mode;
    }

    /**
     * Set the value of mode
     *
     * @param ?string $mode
     *
     * @return self
     */
    public function setMode(?string $mode): self
    {
        $this->mode = $mode;

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