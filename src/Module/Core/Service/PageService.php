<?php
namespace App\Module\Core\Service;

use App\Module\Core\Service\BaseService;

use App\Module\Core\Repository\PageRepository;
use Symfony\Component\HttpFoundation\Request;

class PageService extends BaseService
{
    public function __construct(
        protected BaseService $baseService,
        public PageRepository $repository,
    ) {
        $this->reflectFromParent($baseService);
    }

    public function reOrder(Request $request): void
    {
        $data = $request->request->all();
        foreach ($data['pages'] as $index => $pageId) {
            $page = $this->repository->find($pageId);
            $page->setOrderNumber($index + 1);
            $this->repository->save($page);
        }
    }
}
