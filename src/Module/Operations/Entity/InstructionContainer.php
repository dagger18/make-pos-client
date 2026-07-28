<?php
namespace App\Module\Operations\Entity;

class InstructionContainer
{
    private ?string $type = null;
    private ?string $containerNumber = null;
    private ?string $sealNumber = null;
    private ?string $grossWeight = null;
    private ?string $cbm = null;
    private ?string $tare = null;
    private ?string $packageType = null;
    private ?string $packageCount = null;
    private ?string $vgm = null;
    private ?string $note = null;
    private ?string $method = null;


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
     * Get the value of containerNumber
     *
     * @return ?string
     */
    public function getContainerNumber(): ?string
    {
        return $this->containerNumber;
    }

    /**
     * Set the value of containerNumber
     *
     * @param ?string $containerNumber
     *
     * @return self
     */
    public function setContainerNumber(?string $containerNumber): self
    {
        $this->containerNumber = $containerNumber;

        return $this;
    }

    /**
     * Get the value of sealNumber
     *
     * @return ?string
     */
    public function getSealNumber(): ?string
    {
        return $this->sealNumber;
    }

    /**
     * Set the value of sealNumber
     *
     * @param ?string $sealNumber
     *
     * @return self
     */
    public function setSealNumber(?string $sealNumber): self
    {
        $this->sealNumber = $sealNumber;

        return $this;
    }

    /**
     * Get the value of grossWeight
     *
     * @return ?string
     */
    public function getGrossWeight(): ?string
    {
        return $this->grossWeight;
    }

    /**
     * Set the value of grossWeight
     *
     * @param ?string $grossWeight
     *
     * @return self
     */
    public function setGrossWeight(?string $grossWeight): self
    {
        $this->grossWeight = $grossWeight;

        return $this;
    }

    /**
     * Get the value of cbm
     *
     * @return ?string
     */
    public function getCbm(): ?string
    {
        return $this->cbm;
    }

    /**
     * Set the value of cbm
     *
     * @param ?string $cbm
     *
     * @return self
     */
    public function setCbm(?string $cbm): self
    {
        $this->cbm = $cbm;

        return $this;
    }

    /**
     * Get the value of tare
     *
     * @return ?string
     */
    public function getTare(): ?string
    {
        return $this->tare;
    }

    /**
     * Set the value of tare
     *
     * @param ?string $tare
     *
     * @return self
     */
    public function setTare(?string $tare): self
    {
        $this->tare = $tare;

        return $this;
    }

    /**
     * Get the value of packageType
     *
     * @return ?string
     */
    public function getPackageType(): ?string
    {
        return $this->packageType;
    }

    /**
     * Set the value of packageType
     *
     * @param ?string $packageType
     *
     * @return self
     */
    public function setPackageType(?string $packageType): self
    {
        $this->packageType = $packageType;

        return $this;
    }

    /**
     * Get the value of packageCount
     *
     * @return ?string
     */
    public function getPackageCount(): ?string
    {
        return $this->packageCount;
    }

    /**
     * Set the value of packageCount
     *
     * @param ?string $packageCount
     *
     * @return self
     */
    public function setPackageCount(?string $packageCount): self
    {
        $this->packageCount = $packageCount;

        return $this;
    }

    /**
     * Get the value of vgm
     *
     * @return ?string
     */
    public function getVgm(): ?string
    {
        return $this->vgm;
    }

    /**
     * Set the value of vgm
     *
     * @param ?string $vgm
     *
     * @return self
     */
    public function setVgm(?string $vgm): self
    {
        $this->vgm = $vgm;

        return $this;
    }

    /**
     * Get the value of note
     *
     * @return ?string
     */
    public function getNote(): ?string
    {
        return $this->note;
    }

    /**
     * Set the value of note
     *
     * @param ?string $note
     *
     * @return self
     */
    public function setNote(?string $note): self
    {
        $this->note = $note;

        return $this;
    }

    /**
     * Get the value of method
     *
     * @return ?string
     */
    public function getMethod(): ?string
    {
        return $this->method;
    }

    /**
     * Set the value of method
     *
     * @param ?string $method
     *
     * @return self
     */
    public function setMethod(?string $method): self
    {
        $this->method = $method;

        return $this;
    }
}