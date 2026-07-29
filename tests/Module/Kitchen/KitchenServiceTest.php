<?php
namespace App\Tests\Module\Kitchen;

use App\Module\Kitchen\Entity\KitchenTicket;
use App\Module\Kitchen\Enum\KitchenStatus;
use App\Module\Kitchen\Service\KitchenService;
use PHPUnit\Framework\TestCase;

class KitchenServiceTest extends TestCase
{
    private KitchenService $svc;

    protected function setUp(): void
    {
        $this->svc = new KitchenService();
    }

    public function testAdvancePendingTicketSetsStatusToInProgress(): void
    {
        $ticket = new KitchenTicket();
        $ticket->setStatus(KitchenStatus::Pending);

        $this->svc->advance($ticket);

        $this->assertSame(KitchenStatus::InProgress, $ticket->getStatus());
    }

    public function testAdvanceInProgressTicketSetsStatusToReady(): void
    {
        $ticket = new KitchenTicket();
        $ticket->setStatus(KitchenStatus::InProgress);

        $this->svc->advance($ticket);

        $this->assertSame(KitchenStatus::Ready, $ticket->getStatus());
    }

    public function testAdvanceReadyTicketKeepsStatusReady(): void
    {
        $ticket = new KitchenTicket();
        $ticket->setStatus(KitchenStatus::Ready);

        $this->svc->advance($ticket);

        $this->assertSame(KitchenStatus::Ready, $ticket->getStatus());
    }
}
