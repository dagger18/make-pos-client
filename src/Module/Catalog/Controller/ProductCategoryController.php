<?php

namespace App\Module\Catalog\Controller;

use App\Misc\Attribute\AppModule;
use App\Module\Catalog\Entity\ProductCategory;
use App\Module\Catalog\Repository\ProductCategoryRepository;
use App\Module\Catalog\Service\CatalogService;
use App\Module\Core\Controller\CrudController;
use App\Module\Core\Service\BaseService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/product-category')]
#[IsGranted('ROLE_USER')]
#[AppModule('catalog')]
class ProductCategoryController extends CrudController
{
    public function __construct(
        BaseService $baseService,
        private readonly ProductCategoryRepository $repo,
        private readonly CatalogService $catalogService,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct($baseService);
    }

    #[Route('', methods: ['GET'])]
    public function LIST(Request $request): JsonResponse
    {
        return $this->json($this->repo->getList($request->query->all(), 'list'));
    }

    #[Route('/{id}', methods: ['GET'])]
    public function DETAIL(int $id): JsonResponse
    {
        $category = $this->repo->find($id);
        if (!$category) {
            return $this->json(['error' => 'Not found.'], 404);
        }
        return $this->json($category);
    }

    #[Route('', methods: ['POST'])]
    public function POST(Request $request): JsonResponse
    {
        $data = $request->toArray();
        $parentId = $data['parent_id'] ?? null;
        $parent = null;

        if ($parentId) {
            $parent = $this->repo->find($parentId);
            if (!$parent) {
                return $this->json(['error' => 'Parent category not found.'], 404);
            }
        }

        $this->catalogService->assertCategoryDepth($parent);

        $user = $this->getUser();
        $location = $user->getLocation();
        if (!$location) {
            return $this->json(['error' => 'User has no location assigned.'], 400);
        }

        $category = new ProductCategory();
        $category->setName($data['name'] ?? '');
        $category->setParent($parent);
        $category->setPosition($data['position'] ?? 0);
        $category->setLocation($location);

        $this->em->persist($category);
        $this->em->flush();

        return $this->json(['id' => $category->getId()], 201);
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function PUT(int $id, Request $request): JsonResponse
    {
        $category = $this->repo->find($id);
        if (!$category) {
            return $this->json(['error' => 'Not found.'], 404);
        }

        $data = $request->toArray();
        if (isset($data['name'])) $category->setName($data['name']);
        if (isset($data['position'])) $category->setPosition($data['position']);

        $this->em->flush();

        return $this->json(['id' => $category->getId()]);
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function DELETE(int $id): JsonResponse
    {
        $category = $this->repo->find($id);
        if (!$category) {
            return $this->json(['error' => 'Not found.'], 404);
        }

        $this->em->remove($category);
        $this->em->flush();

        return $this->json(null, 204);
    }
}
