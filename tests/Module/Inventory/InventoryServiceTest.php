<?php
namespace App\Tests\Module\Inventory;

use App\Module\Inventory\Entity\StockLevel;
use App\Module\Inventory\Entity\StockMovement;
use App\Module\Inventory\Enum\MovementType;
use App\Module\Inventory\Service\InventoryService;
use PHPUnit\Framework\TestCase;

class InventoryServiceTest extends TestCase
{
    private InventoryService $svc;

    protected function setUp(): void
    {
        $this->svc = new InventoryService();
    }

    public function testApplyPositiveDeltaIncreasesQuantity(): void
    {
        $sl = new StockLevel();
        $sl->setQuantity(10);

        $this->svc->apply($sl, 5, MovementType::Receive);

        $this->assertSame(15, $sl->getQuantity());
    }

    public function testApplyNegativeDeltaDecreasesQuantity(): void
    {
        $sl = new StockLevel();
        $sl->setQuantity(10);

        $this->svc->apply($sl, -3, MovementType::WriteOff);

        $this->assertSame(7, $sl->getQuantity());
    }

    public function testApplyReturnsStockMovement(): void
    {
        $sl = new StockLevel();
        $sl->setQuantity(0);

        $movement = $this->svc->apply($sl, 20, MovementType::Receive, 'Initial stock');

        $this->assertInstanceOf(StockMovement::class, $movement);
    }

    public function testApplyMovementHasCorrectDeltaAndType(): void
    {
        $sl = new StockLevel();
        $sl->setQuantity(5);

        $movement = $this->svc->apply($sl, -2, MovementType::Adjustment, 'Shrinkage');

        $this->assertSame(-2, $movement->getQuantityDelta());
        $this->assertSame(MovementType::Adjustment, $movement->getType());
        $this->assertSame('Shrinkage', $movement->getNotes());
    }

    public function testApplyMovementLinkedToStockLevel(): void
    {
        $sl = new StockLevel();
        $sl->setQuantity(0);

        $movement = $this->svc->apply($sl, 10, MovementType::Receive);

        $this->assertSame($sl, $movement->getStockLevel());
    }
}
