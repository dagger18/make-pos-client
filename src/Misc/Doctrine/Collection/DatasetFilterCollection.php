<?php
namespace App\Misc\Doctrine\Collection;

use App\Module\Reporting\Entity\DatasetFilter;

class DatasetFilterCollection extends \ArrayObject
{
    public function offsetSet($index, $newval): void
    {
        if (!$newval instanceof DatasetFilter) {
            throw new \InvalidArgumentException("Must be DatasetFilter entity");
        }
        parent::offsetSet($index, $newval);
    }
}