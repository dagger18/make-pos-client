<?php
namespace App\Tests\Module\Table;

use App\Module\Table\Entity\RestaurantTable;
use App\Module\Table\Enum\TableStatus;
use App\Module\Table\Service\TableService;
use PHPUnit\Framework\TestCase;

class TableServiceTest extends TestCase
{
    private TableService $svc;

    protected function setUp(): void
    {
        $this->svc = new TableService();
    }

    public function testSetStatus_setsStatus(): void
    {
        $table = new RestaurantTable();

        $this->svc->setStatus($table, TableStatus::Occupied, null);

        $this->assertSame(TableStatus::Occupied, $table->getStatus());
    }

    public function testSetStatus_setsNotes(): void
    {
        $table = new RestaurantTable();

        $this->svc->setStatus($table, TableStatus::Reserved, 'Reserved for Smith');

        $this->assertSame('Reserved for Smith', $table->getNotes());
    }

    public function testSetStatus_setsNullNotes(): void
    {
        $table = new RestaurantTable();
        $table->setNotes('old notes');

        $this->svc->setStatus($table, TableStatus::Available, null);

        $this->assertNull($table->getNotes());
    }

    public function testSetStatus_toReserved(): void
    {
        $table = new RestaurantTable();

        $this->svc->setStatus($table, TableStatus::Reserved, null);

        $this->assertSame(TableStatus::Reserved, $table->getStatus());
    }

    public function testSetStatus_toCleaning(): void
    {
        $table = new RestaurantTable();

        $this->svc->setStatus($table, TableStatus::Cleaning, 'Post-lunch clean');

        $this->assertSame(TableStatus::Cleaning, $table->getStatus());
        $this->assertSame('Post-lunch clean', $table->getNotes());
    }
}
