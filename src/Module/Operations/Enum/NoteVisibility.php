<?php

namespace App\Module\Operations\Enum;

enum NoteVisibility: string
{
    case Internal = 'INTERNAL';
    case Customer = 'CUSTOMER';
    case All      = 'ALL';
}
