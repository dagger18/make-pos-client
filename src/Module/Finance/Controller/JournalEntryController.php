<?php
namespace App\Module\Finance\Controller;

use App\Module\Finance\Entity\JournalEntry;

use App\Module\Finance\Repository\JournalEntryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use App\Misc\Attribute\AppModule;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/journal')]
#[IsGranted('ROLE_USER')]
#[AppModule('finance')]
class JournalEntryController extends AbstractController
{
    public function __construct(private readonly JournalEntryRepository $repo) {}

    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $ebitNoteId = $request->query->get('ebitNoteId');
        if ($ebitNoteId) {
            $entries = $this->repo->findByEbitNote((int) $ebitNoteId);
            return $this->json(array_map(fn($e) => $this->serialize($e), $entries));
        }
        $entries = $this->repo->findBy([], ['entryDate' => 'DESC']);
        $list    = array_map(fn($e) => $this->serialize($e), $entries);
        return $this->json($this->paginate($list, $request));
    }

    #[Route('/{id}', methods: ['GET'])]
    public function detail(int $id): JsonResponse
    {
        $entry = $this->repo->find($id);
        if (!$entry) throw $this->createNotFoundException();
        return $this->json($this->serialize($entry, true));
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

    private function serialize(JournalEntry $e, bool $withLines = false): array
    {
        $data = [
            'id'            => $e->getId(),
            'journalNumber' => $e->getJournalNumber(),
            'sourceType'    => $e->getSourceType(),
            'entryDate'     => $e->getEntryDate()?->format('Y-m-d'),
            'description'   => $e->getDescription(),
            'isPosted'      => $e->isPosted(),
            'ebitNoteId'    => $e->getEbitNote()?->getId(),
            'ebitNoteCode'  => $e->getEbitNote()?->getCode(),
        ];
        if ($withLines) {
            $data['lines'] = array_map(fn($l) => [
                'account'     => $l->getAccount()?->getCode() . ' ' . $l->getAccount()?->getName(),
                'accountCode' => $l->getAccount()?->getCode(),
                'debit'       => $l->getDebit(),
                'credit'      => $l->getCredit(),
                'baseDebit'   => $l->getBaseDebit(),
                'baseCredit'  => $l->getBaseCredit(),
                'currency'    => $l->getCurrency(),
                'fxRate'      => $l->getFxRate(),
                'description' => $l->getDescription(),
            ], $e->getLines()->toArray());
        }
        return $data;
    }
}
