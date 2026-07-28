<?php
namespace App\Misc\Doctrine\Collection;

use App\Module\Reporting\Entity\DatasetProp;

class DatasetPropCollection extends \ArrayObject
{
    public function offsetSet($index, $newval): void
    {
        if (!$newval instanceof DatasetProp) {
            throw new \InvalidArgumentException("Must be DatasetProp entity");
        }
        parent::offsetSet($index, $newval);
    }
}