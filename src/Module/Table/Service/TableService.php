<?php
namespace App\Module\Table\Service;

use App\Module\Table\Entity\RestaurantTable;
use App\Module\Table\Enum\TableStatus;

class TableService
{
    public function setStatus(RestaurantTable $table, TableStatus $status, ?string $notes): void
    {
        $table->setStatus($status);
        $table->setNotes($notes);
    }
}
