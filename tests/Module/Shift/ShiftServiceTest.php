<?php
namespace App\Tests\Module\Shift;

use App\Module\Shift\Entity\Shift;
use App\Module\Shift\Enum\ShiftStatus;
use App\Module\Shift\Service\ShiftService;
use PHPUnit\Framework\TestCase;

class ShiftServiceTest extends TestCase
{
    private ShiftService $svc;

    protected function setUp(): void
    {
        $this->svc = new ShiftService();
    }

    public function testClose_setsStatusToClosed(): void
    {
        $shift = new Shift();

        $this->svc->close($shift, '150.00', null);

        $this->assertSame(ShiftStatus::Closed, $shift->getStatus());
    }

    public function testClose_setsClosedAt(): void
    {
        $shift = new Shift();

        $this->svc->close($shift, '0', null);

        $this->assertInstanceOf(\DateTime::class, $shift->getClosedAt());
    }

    public function testClose_setsClosingAmount(): void
    {
        $shift = new Shift();

        $this->svc->close($shift, '250.50', null);

        $this->assertSame('250.50', $shift->getClosingAmount());
    }

    public function testClose_setsNotes(): void
    {
        $shift = new Shift();

        $this->svc->close($shift, '0', 'End of day');

        $this->assertSame('End of day', $shift->getNotes());
    }

    public function testClose_setsNullNotes_whenNotProvided(): void
    {
        $shift = new Shift();
        $shift->setNotes('previous notes');

        $this->svc->close($shift, '0', null);

        $this->assertNull($shift->getNotes());
    }
}
