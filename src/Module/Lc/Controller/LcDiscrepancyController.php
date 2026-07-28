<?php
declare(strict_types=1);
namespace App\Module\Lc\Controller;

use App\Attribute\AppModule;
use App\Module\Lc\Entity\LcDiscrepancy;
use App\Module\Lc\Repository\LcDiscrepancyRepository;
use App\Module\Lc\Repository\LetterOfCreditRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AppModule('lc')]
#[Route('/lc/{lcId}/discrepancies')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class LcDiscrepancyController extends AbstractController
{
    public function __construct(
        private readonly LcDiscrepancyRepository $repo,
        private readonly LetterOfCreditRepository $lcRepo,
        private readonly EntityManagerInterface   $em,
    ) {}

    #[Route('', methods: ['GET'])]
    public function list(int $lcId): JsonResponse
    {
        $items = $this->repo->findByLc($lcId);
        return $this->json(array_map(fn($d) => $d->toArray(), $items));
    }

    #[Route('', methods: ['POST'])]
    public function create(int $lcId, Request $request): JsonResponse
    {
        $lc = $this->lcRepo->find($lcId);
        if (!$lc) return $this->json(['error' => $this->trans('LC not found')], 404);

        $data = json_decode($request->getContent(), true);
        $disc = (new LcDiscrepancy())
            ->setLc($lc)
            ->setCheckCode($data['checkCode'] ?? 'MANUAL')
            ->setSeverity($data['severity'] ?? 'FATAL')
            ->setDescription($data['description'])
            ->setDocumentType($data['documentType'] ?? null);

        $this->em->persist($disc);
        $this->em->flush();
        return $this->json($disc->toArray(), 201);
    }

    #[Route('/{id}/resolve', methods: ['POST'])]
    public function resolve(int $lcId, int $id, Request $request): JsonResponse
    {
        $disc = $this->repo->find($id);
        if (!$disc || $disc->getLc()->getId() !== $lcId) return $this->json(['error' => $this->trans('Not found')], 404);

        $data = json_decode($request->getContent(), true);
        $disc->setResolvedAt(new \DateTime())
             ->setResolution($data['resolution'] ?? '');

        $this->em->flush();
        return $this->json($disc->toArray());
    }

    #[Route('/{id}/waive', methods: ['POST'])]
    public function waive(int $lcId, int $id, Request $request): JsonResponse
    {
        $disc = $this->repo->find($id);
        if (!$disc || $disc->getLc()->getId() !== $lcId) return $this->json(['error' => $this->trans('Not found')], 404);

        $data = json_decode($request->getContent(), true);
        $disc->setIsWaived(true)
             ->setWaivedByBank((bool)($data['waivedByBank'] ?? false))
             ->setResolution($data['resolution'] ?? 'Waived');

        $this->em->flush();
        return $this->json($disc->toArray());
    }
}
