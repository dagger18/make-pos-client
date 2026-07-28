<?php

namespace App\Misc\Traits;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;

trait EntityDateTimeAbleTrait
{
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdDate = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedDate = null;

    public function getCreatedDate(): ?\DateTimeInterface
    {
        return $this->createdDate;
    }

    public function setCreatedDate(\DateTimeInterface $createdDate): self
    {
        $this->createdDate = $createdDate;

        return $this;
    }

    public function getUpdatedDate(): ?\DateTimeInterface
    {
        return $this->updatedDate;
    }

    public function setUpdatedDate(?\DateTimeInterface $updatedDate): self
    {
        $this->updatedDate = $updatedDate;

        return $this;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        if (!$this->createdDate) {
            $this->createdDate = new \DateTime('now');
        }

        if (!$this->updatedDate) {
            $this->updatedDate = $this->createdDate;
        }
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        
        // user last action timer save, ignore it
        forEach([
            'lastPing', 
            'lastShipmentView',
            'lastClientView',
            'lastProviderView',
            'lastAccountingView'
        ] as $prop) {
            if(property_exists($this, $prop)) {
                if($this->$prop 
                    && abs($this->$prop->getTimestamp() - (new \DateTime("now"))->getTimestamp()) < 2
                ) {
                    return;
                }
            }
        }
        
        $this->updatedDate = new \DateTime("now");
    }
}