<?php
namespace App\MessageHandler;

use App\Message\NotificationTriggerMessage;
use App\Module\Notification\Service\NotificationGeneratorService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class NotificationTriggerMessageHandler
{
    public function __construct(
        private readonly NotificationGeneratorService $generator,
    ) {}

    public function __invoke(NotificationTriggerMessage $message): void
    {
        // POS-specific notification handling to be implemented per module.
    }
}
