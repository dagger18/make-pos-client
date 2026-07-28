<?php

namespace App\Module\Catalog\Controller;

use App\Misc\Attribute\AppModule;
use App\Module\Catalog\Entity\Modifier;
use App\Module\Catalog\Entity\ModifierGroup;
use App\Module\Catalog\Repository\ModifierGroupRepository;
use App\Module\Catalog\Repository\ModifierRepository;
use App\Module\Core\Controller\CrudController;
use App\Module\Core\Service\BaseService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/modifier-group')]
#[IsGranted('ROLE_USER')]
#[AppModule('catalog')]
class ModifierGroupController extends CrudController
{
    public function __construct(
        BaseService $baseService,
        private readonly ModifierGroupRepository $repo,
        private readonly ModifierRepository $modifierRepo,
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
        $group = $this->repo->find($id);
        if (!$group) {
            return $this->json(['error' => 'Not found.'], 404);
        }
        return $this->json($group);
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

        $group = new ModifierGroup();
        $group->setLocation($location);
        $group->setName($data['name'] ?? '');
        $group->setRequired($data['required'] ?? false);
        $group->setMin($data['min'] ?? 0);
        $group->setMax($data['max'] ?? null);
        $group->setPosition($data['position'] ?? 0);

        foreach ($data['modifiers'] ?? [] as $i => $mData) {
            $modifier = new Modifier();
            $modifier->setName($mData['name'] ?? '');
            $modifier->setPriceDelta($mData['price_delta'] ?? 0);
            $modifier->setPosition($mData['position'] ?? $i);
            $group->addModifier($modifier);
        }

        $this->em->persist($group);
        $this->em->flush();

        return $this->json(['id' => $group->getId()], 201);
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function PUT(int $id, Request $request): JsonResponse
    {
        $group = $this->repo->find($id);
        if (!$group) {
            return $this->json(['error' => 'Not found.'], 404);
        }

        $data = $request->toArray();
        if (isset($data['name'])) $group->setName($data['name']);
        if (isset($data['required'])) $group->setRequired($data['required']);
        if (array_key_exists('min', $data)) $group->setMin($data['min']);
        if (array_key_exists('max', $data)) $group->setMax($data['max']);
        if (isset($data['position'])) $group->setPosition($data['position']);

        $this->em->flush();

        return $this->json(['id' => $group->getId()]);
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function DELETE(int $id): JsonResponse
    {
        $group = $this->repo->find($id);
        if (!$group) {
            return $this->json(['error' => 'Not found.'], 404);
        }

        $this->em->remove($group);
        $this->em->flush();

        return $this->json(null, 204);
    }

    #[Route('/{groupId}/modifier', methods: ['POST'])]
    public function ADD_MODIFIER(int $groupId, Request $request): JsonResponse
    {
        $group = $this->repo->find($groupId);
        if (!$group) {
            return $this->json(['error' => 'Modifier group not found.'], 404);
        }

        $data = $request->toArray();
        $modifier = new Modifier();
        $modifier->setName($data['name'] ?? '');
        $modifier->setPriceDelta($data['price_delta'] ?? 0);
        $modifier->setPosition($data['position'] ?? 0);
        $group->addModifier($modifier);

        $this->em->flush();

        return $this->json(['id' => $modifier->getId()], 201);
    }

    #[Route('/{groupId}/modifier/{mid}', methods: ['PUT'])]
    public function UPDATE_MODIFIER(int $groupId, int $mid, Request $request): JsonResponse
    {
        $modifier = $this->modifierRepo->find($mid);
        if (!$modifier || $modifier->getGroup()?->getId() !== $groupId) {
            return $this->json(['error' => 'Modifier not found.'], 404);
        }

        $data = $request->toArray();
        if (isset($data['name'])) $modifier->setName($data['name']);
        if (isset($data['price_delta'])) $modifier->setPriceDelta($data['price_delta']);
        if (isset($data['position'])) $modifier->setPosition($data['position']);

        $this->em->flush();

        return $this->json(['id' => $modifier->getId()]);
    }

    #[Route('/{groupId}/modifier/{mid}', methods: ['DELETE'])]
    public function DELETE_MODIFIER(int $groupId, int $mid): JsonResponse
    {
        $modifier = $this->modifierRepo->find($mid);
        if (!$modifier || $modifier->getGroup()?->getId() !== $groupId) {
            return $this->json(['error' => 'Modifier not found.'], 404);
        }

        $this->em->remove($modifier);
        $this->em->flush();

        return $this->json(null, 204);
    }
}
