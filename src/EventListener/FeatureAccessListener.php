<?php
namespace App\EventListener;

use App\Misc\Attribute\RequireFeature;
use App\Module\Core\Service\FeatureService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsEventListener(event: KernelEvents::CONTROLLER)]
class FeatureAccessListener
{
    public function __construct(
        private readonly FeatureService      $featureService,
        private readonly TranslatorInterface $translator,
    ) {}

    public function __invoke(ControllerEvent $event): void
    {
        $controller = $event->getController();

        if (is_array($controller)) {
            [$object, $method] = $controller;
            $reflClass  = new \ReflectionClass($object);
            $reflMethod = $reflClass->getMethod($method);
            $attrs = array_merge(
                $reflClass->getAttributes(RequireFeature::class),
                $reflMethod->getAttributes(RequireFeature::class),
            );
        } elseif (is_callable($controller)) {
            $reflMethod = new \ReflectionFunction(\Closure::fromCallable($controller));
            $attrs = $reflMethod->getAttributes(RequireFeature::class);
        } else {
            return;
        }

        foreach ($attrs as $attr) {
            $instance = $attr->newInstance();
            if (!$this->featureService->isEnabled($instance->feature)) {
                $event->setController(function () use ($instance) {
                    return new JsonResponse(
                        ['error' => $this->translator->trans('Feature %feature% is not enabled on this plan.', ['%feature%' => $instance->feature->name])],
                        Response::HTTP_FORBIDDEN,
                    );
                });
                return;
            }
        }
    }
}
