<?php
declare(strict_types=1);
namespace App\Module\Lc\Controller;

use App\Attribute\AppModule;
use App\Module\Lc\Entity\LcPresentation;
use App\Module\Lc\Repository\LcPresentationRepository;
use App\Module\Lc\Repository\LetterOfCreditRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AppModule('lc')]
#[Route('/lc/{lcId}/presentations')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class LcPresentationController extends AbstractController
{
    public function __construct(
        private readonly LcPresentationRepository $repo,
        private readonly LetterOfCreditRepository $lcRepo,
        private readonly EntityManagerInterface   $em,
    ) {}

    #[Route('', methods: ['GET'])]
    public function list(int $lcId): JsonResponse
    {
        $items = $this->repo->findByLc($lcId);
        return $this->json(array_map(fn($p) => $p->toArray(), $items));
    }

    #[Route('', methods: ['POST'])]
    public function create(int $lcId, Request $request): JsonResponse
    {
        $lc = $this->lcRepo->find($lcId);
        if (!$lc) return $this->json(['error' => $this->trans('LC not found')], 404);

        $data = json_decode($request->getContent(), true);
        $pres = (new LcPresentation())
            ->setLc($lc)
            ->setPresentedToBankName($data['presentedToBankName'])
            ->setPresentedAt(new \DateTime($data['presentedAt'] ?? 'now'))
            ->setDocumentsPresented($data['documentsPresented'] ?? [])
            ->setHasDiscrepancies((bool)($data['hasDiscrepancies'] ?? false))
            ->setBankResponse($data['bankResponse'] ?? null)
            ->setNotes($data['notes'] ?? null);

        if (!empty($data['bankResponseDate'])) {
            $pres->setBankResponseDate(new \DateTime($data['bankResponseDate']));
        }
        if (!empty($data['paymentDate'])) {
            $pres->setPaymentDate(new \DateTime($data['paymentDate']));
        }
        if (!empty($data['paymentAmount'])) {
            $pres->setPaymentAmount((string)$data['paymentAmount']);
        }

        // Auto-advance LC status when presentation is recorded
        if ($lc->getStatus() === 'DOCUMENTS_PREPARED') {
            $lc->setStatus('PRESENTED');
        }

        $this->em->persist($pres);
        $this->em->flush();
        return $this->json($pres->toArray(), 201);
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function update(int $lcId, int $id, Request $request): JsonResponse
    {
        $pres = $this->repo->find($id);
        if (!$pres || $pres->getLc()->getId() !== $lcId) return $this->json(['error' => $this->trans('Not found')], 404);

        $data = json_decode($request->getContent(), true);
        if (isset($data['bankResponse']))     $pres->setBankResponse($data['bankResponse']);
        if (isset($data['hasDiscrepancies'])) $pres->setHasDiscrepancies((bool)$data['hasDiscrepancies']);
        if (array_key_exists('notes', $data)) $pres->setNotes($data['notes']);
        if (!empty($data['bankResponseDate'])) {
            $pres->setBankResponseDate(new \DateTime($data['bankResponseDate']));
        }
        if (!empty($data['paymentDate'])) {
            $pres->setPaymentDate(new \DateTime($data['paymentDate']));
        }
        if (!empty($data['paymentAmount'])) {
            $pres->setPaymentAmount((string)$data['paymentAmount']);
        }

        // Auto-advance LC status on COMPLIANT bank response with payment
        $lc = $pres->getLc();
        if ($data['bankResponse'] === 'COMPLIANT' && !empty($data['paymentDate'])) {
            $lc->setStatus('NEGOTIATED');
        }

        $this->em->flush();
        return $this->json($pres->toArray());
    }
}
