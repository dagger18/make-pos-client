<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Module\Core\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 7)]
final class LocaleSubscriber
{
    public function __construct(private readonly Security $security) {}

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $user = $this->security->getUser();
        if ($user instanceof User && $user->getLanguage() !== 'en') {
            $event->getRequest()->setLocale($user->getLanguage());
        }
    }
}
