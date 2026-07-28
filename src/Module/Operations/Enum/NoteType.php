<?php

namespace App\Module\Operations\Enum;

enum NoteType: string
{
    case Internal = 'INTERNAL';
    case Customer = 'CUSTOMER';
    case Agent    = 'AGENT';
    case System   = 'SYSTEM';
}
