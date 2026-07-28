<?php

namespace App\EventSubscribers\Kernel;

use App\Module\Core\Service\RequestService;
use App\Misc\Exception\CacheHitResponse;
use App\Misc\Exception\ApiExceptionInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class KernelExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private RequestService $requestService,
        private ParameterBagInterface $params
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }

    public function onKernelException(ExceptionEvent $event)
    {
        $exception = $event->getThrowable();
        $response = new JsonResponse();
        //dd($exception);
        if ($exception instanceof CacheHitResponse) {
            $this->requestService->exception = $exception;
            $event->allowCustomResponseCode();
            $response->setStatusCode(($exception->getCode() == 500) ? 200 : $exception->getCode());
            $response->setData(json_decode($exception->getMessage(), 1));
            $response->headers->set('X-Cache-Hit', 'True');
            $event->setResponse($response);
        } else if ($exception instanceof ApiExceptionInterface) {
            
            $responseData = $exception->getMessages();
            
            if($this->params->get('kernel.environment') == 'dev') {
                $responseData['trace'] = [
                    'position' => $exception->getLine() . " of " . $exception->getFile(),
                    'stack' => $exception->getTrace()
                ];
            }

            $response->setStatusCode($responseData['code']);
            $response->setData($responseData);
            $event->setResponse($response);
            $this->requestService->exception = $exception;
            $this->requestService->exceptionCode = $responseData['code'];
            
            $this
            ->requestService
            ->saveLog(
                $event->getResponse()->getStatusCode(),
                $event->getResponse()->getContent(),
                null,
                true
            );
        } else if($exception instanceof UserNotFoundException){
            dd($exception);
        } else if($exception instanceof NotFoundHttpException){
            // doing nothing
        } else {
            try {
                $this->requestService->exception = $exception;
                $this->requestService->exceptionCode = Response::HTTP_INTERNAL_SERVER_ERROR;
                $this
                ->requestService
                ->saveLog(
                    $event->getResponse() ? $event->getResponse()->getStatusCode() : 0,
                    $event->getResponse() ? $event->getResponse()->getContent() : '',
                    true
                );
            } catch(\Exception $e) {
                header('Access-Control-Allow-Origin: *');dd($e);
            }
        }
    }
}
