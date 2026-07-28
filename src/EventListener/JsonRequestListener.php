<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 100)]
class JsonRequestListener
{
    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!str_contains($request->headers->get('Content-Type', ''), 'application/json')) {
            return;
        }

        $content = $request->getContent();
        if (empty($content)) {
            return;
        }

        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            return;
        }

        $request->request->replace($data);
    }
}
