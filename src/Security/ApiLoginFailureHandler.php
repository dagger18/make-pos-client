<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\DefaultAuthenticationFailureHandler;
use Symfony\Contracts\Translation\TranslatorInterface;

class ApiLoginFailureHandler extends DefaultAuthenticationFailureHandler {
    public function __construct(private readonly TranslatorInterface $translator) {}

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        // todo: if request is api auth only <== what ??
        // todo: better todo description next time
        return new JsonResponse(['error' => $this->translator->trans('Wrong credentials')], JsonResponse::HTTP_OK);
    }
}