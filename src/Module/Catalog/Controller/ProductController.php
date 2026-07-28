<?php

namespace App\Module\Catalog\Controller;

use App\Misc\Attribute\AppModule;
use App\Module\Catalog\Entity\Product;
use App\Module\Catalog\Entity\ProductModifier;
use App\Module\Catalog\Enum\ProductType;
use App\Module\Catalog\Repository\ModifierGroupRepository;
use App\Module\Catalog\Repository\ProductCategoryRepository;
use App\Module\Catalog\Repository\ProductModifierRepository;
use App\Module\Catalog\Repository\ProductRepository;
use App\Module\Catalog\Service\CatalogService;
use App\Module\Core\Controller\CrudController;
use App\Module\Core\Service\BaseService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/product')]
#[IsGranted('ROLE_USER')]
#[AppModule('catalog')]
class ProductController extends CrudController
{
    public function __construct(
        BaseService $baseService,
        private readonly ProductRepository $repo,
        private readonly CatalogService $catalogService,
        private readonly ProductCategoryRepository $categoryRepo,
        private readonly ModifierGroupRepository $modifierGroupRepo,
        private readonly ProductModifierRepository $productModifierRepo,
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
        $product = $this->repo->find($id);
        if (!$product) {
            return $this->json(['error' => 'Not found.'], 404);
        }
        return $this->json($product);
    }

    #[Route('', methods: ['POST'])]
    public function POST(Request $request): JsonResponse
    {
        $data = $request->toArray();
        $user = $this->getUser();
        $location = $user->getLocation();
        if (!$location) {
            return $this->json(['error' => 'User has no location assigned.'], 400);
        }

        $this->catalogService->assertSkuUnique($data['sku'] ?? '', $location->getId());

        $product = new Product();
        $product->setLocation($location);
        $product->setSku($data['sku'] ?? '');
        $product->setName($data['name'] ?? '');
        $product->setDescription($data['description'] ?? null);
        $product->setPrice($data['price'] ?? '0');
        $product->setCost($data['cost'] ?? null);
        $product->setType(ProductType::from($data['type'] ?? 'retail'));
        $product->setActive($data['active'] ?? true);

        if (!empty($data['category_id'])) {
            $category = $this->categoryRepo->find($data['category_id']);
            if ($category) $product->setCategory($category);
        }

        $this->em->persist($product);
        $this->em->flush();

        return $this->json(['id' => $product->getId()], 201);
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function PUT(int $id, Request $request): JsonResponse
    {
        $product = $this->repo->find($id);
        if (!$product) {
            return $this->json(['error' => 'Not found.'], 404);
        }

        $data = $request->toArray();
        if (isset($data['name'])) $product->setName($data['name']);
        if (isset($data['description'])) $product->setDescription($data['description']);
        if (isset($data['price'])) $product->setPrice($data['price']);
        if (array_key_exists('cost', $data)) $product->setCost($data['cost']);
        if (isset($data['type'])) $product->setType(ProductType::from($data['type']));
        if (isset($data['active'])) $product->setActive((bool) $data['active']);
        if (array_key_exists('category_id', $data)) {
            $product->setCategory($data['category_id'] ? $this->categoryRepo->find($data['category_id']) : null);
        }

        $this->em->flush();

        return $this->json(['id' => $product->getId()]);
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function DELETE(int $id): JsonResponse
    {
        $product = $this->repo->find($id);
        if (!$product) {
            return $this->json(['error' => 'Not found.'], 404);
        }

        $this->em->remove($product);
        $this->em->flush();

        return $this->json(null, 204);
    }

    #[Route('/{id}/modifier-group/{gid}', methods: ['POST'])]
    public function ATTACH_MODIFIER_GROUP(int $id, int $gid): JsonResponse
    {
        $product = $this->repo->find($id);
        if (!$product) return $this->json(['error' => 'Product not found.'], 404);

        if ($product->getType() !== ProductType::Food) {
            return $this->json(['error' => 'Modifier groups can only be attached to food products.'], 400);
        }

        $group = $this->modifierGroupRepo->find($gid);
        if (!$group) return $this->json(['error' => 'Modifier group not found.'], 404);

        $product->addModifierGroup($group);
        $this->em->flush();

        return $this->json(null, 204);
    }

    #[Route('/{id}/modifier-group/{gid}', methods: ['DELETE'])]
    public function DETACH_MODIFIER_GROUP(int $id, int $gid): JsonResponse
    {
        $product = $this->repo->find($id);
        if (!$product) return $this->json(['error' => 'Product not found.'], 404);

        $group = $this->modifierGroupRepo->find($gid);
        if ($group) $product->removeModifierGroup($group);

        $this->em->flush();

        return $this->json(null, 204);
    }

    #[Route('/{id}/modifier', methods: ['POST'])]
    public function ADD_MODIFIER(int $id, Request $request): JsonResponse
    {
        $product = $this->repo->find($id);
        if (!$product) return $this->json(['error' => 'Product not found.'], 404);

        if ($product->getType() !== ProductType::Food) {
            return $this->json(['error' => 'Modifiers can only be added to food products.'], 400);
        }

        $data = $request->toArray();
        $modifier = new ProductModifier();
        $modifier->setProduct($product);
        $modifier->setName($data['name'] ?? '');
        $modifier->setPriceDelta($data['price_delta'] ?? 0);
        $modifier->setPosition($data['position'] ?? 0);

        $this->em->persist($modifier);
        $this->em->flush();

        return $this->json(['id' => $modifier->getId()], 201);
    }

    #[Route('/{id}/modifier/{mid}', methods: ['PUT'])]
    public function UPDATE_MODIFIER(int $id, int $mid, Request $request): JsonResponse
    {
        $modifier = $this->productModifierRepo->find($mid);
        if (!$modifier || $modifier->getProduct()?->getId() !== $id) {
            return $this->json(['error' => 'Modifier not found.'], 404);
        }

        $data = $request->toArray();
        if (isset($data['name'])) $modifier->setName($data['name']);
        if (isset($data['price_delta'])) $modifier->setPriceDelta($data['price_delta']);
        if (isset($data['position'])) $modifier->setPosition($data['position']);

        $this->em->flush();

        return $this->json(['id' => $modifier->getId()]);
    }

    #[Route('/{id}/modifier/{mid}', methods: ['DELETE'])]
    public function DELETE_MODIFIER(int $id, int $mid): JsonResponse
    {
        $modifier = $this->productModifierRepo->find($mid);
        if (!$modifier || $modifier->getProduct()?->getId() !== $id) {
            return $this->json(['error' => 'Modifier not found.'], 404);
        }

        $this->em->remove($modifier);
        $this->em->flush();

        return $this->json(null, 204);
    }
}
