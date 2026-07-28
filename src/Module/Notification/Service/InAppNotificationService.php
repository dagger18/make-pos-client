<?php
namespace App\Module\Notification\Service;

use App\Module\Notification\Entity\InAppNotification;
use App\Module\Core\Entity\User;
use App\Module\Notification\Repository\InAppNotificationRepository;

class InAppNotificationService
{
    public function __construct(
        private readonly InAppNotificationRepository $repository,
    ) {}

    public function create(
        User    $user,
        string  $title,
        string  $body,
        string  $priority = 'NORMAL',
        ?string $ruleKey = null,
        ?string $actionUrl = null,
    ): InAppNotification {
        $n = new InAppNotification();
        $n->setUser($user);
        $n->setTitle($title);
        $n->setBody($body);
        $n->setPriority($priority);
        $n->setRuleKey($ruleKey);
        $n->setActionUrl($actionUrl);
        return $this->repository->save($n);
    }

    public function getPagedForUser(int $userId, int $page): array
    {
        return $this->repository->findPagedForUser($userId, $page);
    }

    public function markRead(int $userId, ?array $ids = null): void
    {
        $this->repository->markReadForUser($userId, $ids);
    }

    public function countUnread(int $userId): int
    {
        return $this->repository->countUnreadForUser($userId);
    }
}
