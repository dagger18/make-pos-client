<?php

namespace App\EventSubscribers\Kernel;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class KernelRequestSubscriber implements EventSubscriberInterface
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    public function __construct(
        ContainerInterface $container
    ) {
        $this->container = $container;
    }

    public function setLanguage($event)
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if ($locale = $event->getRequest()->headers->get('X-Language')) {
            $locale = strtolower($locale);
            $event->getRequest()->setLocale($locale);
        }
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST => [
                ['setLanguage', 16]
            ],
        ];
    }
}
