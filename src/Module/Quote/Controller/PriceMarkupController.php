<?php

namespace App\Module\Quote\Controller;

use App\Module\Core\Controller\CrudController;

use App\Misc\Traits\Controller\DeleteActionTrait;
use App\Misc\Traits\Controller\GetActionTrait;
use App\Misc\Traits\Controller\PostActionTrait;
use App\Misc\Traits\Controller\PutActionTrait;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use App\Misc\Attribute\AppModule;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/price-markup')]
#[IsGranted('ROLE_USER')]
#[AppModule('quote')]
class PriceMarkupController extends CrudController
{
    use GetActionTrait;
    use PostActionTrait;
    use PutActionTrait;
    use DeleteActionTrait;

    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        return $this->json($this->repository->getList($request, 'list'));
    }
}
