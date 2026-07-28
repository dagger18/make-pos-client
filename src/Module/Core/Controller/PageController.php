<?php

namespace App\Module\Core\Controller;

use App\Module\Core\Controller\CrudController;

use App\Misc\Attribute\Log;
use App\Misc\Traits\Controller\DeleteActionTrait;
use App\Misc\Traits\Controller\GetActionTrait;
use App\Misc\Traits\Controller\PostActionTrait;
use App\Misc\Traits\Controller\PutActionTrait;
use App\Module\Core\Service\BaseService;
use App\Module\Core\Service\PageService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use App\Misc\Attribute\AppModule;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/page')]
#[IsGranted('ROLE_USER')]
#[AppModule('core')]
class PageController extends CrudController
{
    use GetActionTrait;
    use PostActionTrait;
    use PutActionTrait;
    use DeleteActionTrait;

    public function __construct(
        protected BaseService $baseService,
        protected PageService $pageService,
    ) {}

    #[Route('/re-order', methods: ['POST'])]
    #[Log]
    public function reOrder(Request $request): JsonResponse
    {
        $this->pageService->reOrder($request);
        return $this->json(['success' => true]);
    }
}
