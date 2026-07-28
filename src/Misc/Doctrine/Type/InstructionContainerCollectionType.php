<?php

namespace App\Misc\Doctrine\Type;

use App\Module\Operations\Entity\InstructionContainer;

class InstructionContainerCollectionType extends BaseCollectionType
{
    protected $properties = [
        'type',
        'containerNumber',
        'sealNumber',
        'grossWeight',
        'cbm',
        'tare',
        'packageType',
        'packageCount',
        'vgm',
        'note',
        'method',
    ]; 
    protected $entityClass = InstructionContainer::class;

    public function getName() {
      return 'InstructionContainerCollection';
    }
}