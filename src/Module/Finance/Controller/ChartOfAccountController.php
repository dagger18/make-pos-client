<?php
namespace App\Module\Finance\Controller;

use App\Module\Finance\Entity\ChartOfAccount;
use App\Module\Finance\Repository\ChartOfAccountRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Misc\Attribute\AppModule;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/chart-of-account')]
#[IsGranted('ROLE_USER')]
#[AppModule('finance')]
class ChartOfAccountController extends AbstractController
{
    public function __construct(private readonly ChartOfAccountRepository $repo) {}

    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $accounts = $this->repo->findBy([], ['code' => 'ASC']);
        $list = array_map(fn($a) => [
            'id'          => $a->getId(),
            'code'        => $a->getCode(),
            'name'        => $a->getName(),
            'accountType' => $a->getAccountType(),
            'isActive'    => $a->isActive(),
        ], $accounts);
        return $this->json($this->paginate($list, $request));
    }

    private function paginate(array $items, Request $request): array
    {
        $limit = (int) ($request->query->get('limit') ?? 50);
        $page  = max(1, (int) ($request->query->get('page') ?? 1));
        $total = count($items);
        if ($limit <= 0) {
            return ['list' => $items, 'total' => $total, 'totalPages' => 1, 'currentPage' => 1];
        }
        return [
            'list'        => array_values(array_slice($items, ($page - 1) * $limit, $limit)),
            'total'       => $total,
            'totalPages'  => (int) ceil($total / $limit),
            'currentPage' => $page,
        ];
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $account = $this->repo->find($id);
        if (!$account) throw $this->createNotFoundException();
        $body = json_decode($request->getContent(), true) ?? [];
        if (isset($body['name'])) $account->setName($body['name']);
        if (isset($body['isActive'])) $account->setIsActive((bool) $body['isActive']);
        $this->repo->save($account);
        return $this->json(['id' => $account->getId(), 'code' => $account->getCode(), 'name' => $account->getName()]);
    }
}
