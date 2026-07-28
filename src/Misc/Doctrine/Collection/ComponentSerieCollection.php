<?php
namespace App\Misc\Doctrine\Collection;

use App\Module\Core\Entity\ComponentSerie;

class ComponentSerieCollection extends \ArrayObject
{
    public function offsetSet($index, $newval): void
    {
        if (!$newval instanceof ComponentSerie) {
            throw new \InvalidArgumentException("Must be ComponentSerie entity");
        }
        parent::offsetSet($index, $newval);
    }
}