<?php

declare(strict_types=1);

namespace App\EventSubscribers;

use Symfony\Component\AssetMapper\Event\PreAssetsCompileEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class AssetMapCompileSubsriber implements EventSubscriberInterface
{

    public function __construct(
    )
    {
    }
    public static function getSubscribedEvents(): array
    {
        return [
            PreAssetsCompileEvent::class => 'preAssetsCompile'
        ];
    }
    public function preAssetsCompile(PreAssetsCompileEvent $event): void
    {
        
       //todo: minify and obstrucate js here
        /** @var FilesAwareInterface $subject */
        //$subject = $event->getSubject();
    }
}
