<?php
namespace App\Message;

class NotificationTriggerMessage
{
    public function __construct(
        public readonly string $eventType,
        public readonly int    $entityId,
        public readonly array  $context = [],
    ) {}
}
