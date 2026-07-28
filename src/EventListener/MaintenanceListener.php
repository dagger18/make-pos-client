<?php

namespace App\EventListener;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 200)]
class MaintenanceListener
{
    public function __construct(
        #[Autowire(env: 'bool:IS_MAINTENANCE')]
        private readonly bool $isMaintenance,
    ) {}

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        if (!$this->isMaintenance) {
            return;
        }
        $response = new JsonResponse([
            'maintenance' => true,
            'message'     => 'Server is under maintenance. Please try again in 5–10 minutes.',
        ], 503);
        $response->headers->set('Retry-After', '600');
        $event->setResponse($response);
    }
}
