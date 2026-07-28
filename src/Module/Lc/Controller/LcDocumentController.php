<?php
declare(strict_types=1);
namespace App\Module\Lc\Controller;

use App\Attribute\AppModule;
use App\Module\Core\Entity\Media;
use App\Module\Core\Entity\User;
use App\Module\Lc\Entity\LcDocumentRequirement;
use App\Module\Lc\Repository\LcDocumentRequirementRepository;
use App\Module\Lc\Repository\LetterOfCreditRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AppModule('lc')]
#[Route('/lc/{lcId}/documents')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class LcDocumentController extends AbstractController
{
    public function __construct(
        private readonly LcDocumentRequirementRepository $repo,
        private readonly LetterOfCreditRepository        $lcRepo,
        private readonly EntityManagerInterface          $em,
    ) {}

    #[Route('', methods: ['GET'])]
    public function list(int $lcId): JsonResponse
    {
        $docs = $this->repo->findByLc($lcId);
        return $this->json(array_map(fn($d) => $d->toArray(), $docs));
    }

    #[Route('', methods: ['POST'])]
    public function create(int $lcId, Request $request): JsonResponse
    {
        $lc = $this->lcRepo->find($lcId);
        if (!$lc) return $this->json(['error' => $this->trans('LC not found')], 404);

        $data = json_decode($request->getContent(), true);
        $doc  = (new LcDocumentRequirement())
            ->setLc($lc)
            ->setDocType($data['docType'])
            ->setQuantityOriginals((int)($data['quantityOriginals'] ?? 1))
            ->setQuantityCopies((int)($data['quantityCopies'] ?? 0))
            ->setSpecificWording($data['specificWording'] ?? '');

        $this->em->persist($doc);
        $this->em->flush();
        return $this->json($doc->toArray(), 201);
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function update(int $lcId, int $id, Request $request): JsonResponse
    {
        $doc = $this->repo->find($id);
        if (!$doc || $doc->getLc()->getId() !== $lcId) return $this->json(['error' => $this->trans('Not found')], 404);

        $data = json_decode($request->getContent(), true);
        if (isset($data['docType']))             $doc->setDocType($data['docType']);
        if (isset($data['quantityOriginals']))   $doc->setQuantityOriginals((int)$data['quantityOriginals']);
        if (isset($data['quantityCopies']))      $doc->setQuantityCopies((int)$data['quantityCopies']);
        if (isset($data['specificWording']))     $doc->setSpecificWording($data['specificWording']);
        if (isset($data['isReady']))             $doc->setIsReady((bool)$data['isReady']);
        if (isset($data['complianceChecked']))   $doc->setComplianceChecked((bool)$data['complianceChecked']);
        if (array_key_exists('complianceNotes', $data)) $doc->setComplianceNotes($data['complianceNotes']);
        if (isset($data['mediaId'])) {
            $doc->setMedia($data['mediaId'] ? $this->em->getReference(Media::class, $data['mediaId']) : null);
        }

        $this->em->flush();
        return $this->json($doc->toArray());
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(int $lcId, int $id): JsonResponse
    {
        $doc = $this->repo->find($id);
        if (!$doc || $doc->getLc()->getId() !== $lcId) return $this->json(['error' => $this->trans('Not found')], 404);
        $this->em->remove($doc);
        $this->em->flush();
        return $this->json(null, 204);
    }

    #[Route('/{id}/mark-ready', methods: ['POST'])]
    public function markReady(int $lcId, int $id, Request $request): JsonResponse
    {
        $doc = $this->repo->find($id);
        if (!$doc || $doc->getLc()->getId() !== $lcId) return $this->json(['error' => $this->trans('Not found')], 404);

        $data = json_decode($request->getContent(), true);
        $doc->setIsReady(true);
        $doc->setComplianceChecked((bool)($data['complianceChecked'] ?? false));
        if (!empty($data['complianceNotes'])) {
            $doc->setComplianceNotes($data['complianceNotes']);
        }
        if (!empty($data['checkedByUserId'])) {
            $doc->setComplianceCheckedBy($this->em->getReference(User::class, $data['checkedByUserId']));
        }

        $this->em->flush();
        return $this->json($doc->toArray());
    }
}
