<?php
namespace App\Module\Quote\Controller;

use App\Misc\Attribute\AppModule;
use App\Module\Quote\Repository\RateImportJobRepository;
use App\Module\Quote\Repository\RateImportRowRepository;
use App\Module\Quote\Service\RateImportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

#[Route('/rate-import')]
#[IsGranted('ROLE_USER')]
#[AppModule('quote')]
class RateImportController extends AbstractController
{
    public function __construct(
        private RateImportJobRepository $jobRepository,
        private RateImportRowRepository $rowRepository,
        private RateImportService $importService,
        private NormalizerInterface $serializer,
    ) {}

    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $jobs = $this->jobRepository->findBy([], ['id' => 'DESC']);
        $list = $this->serializer->normalize($jobs, null, ['groups' => ['list']]);
        return $this->json($this->paginate($list, $request));
    }

    #[Route('', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        $file = $request->files->get('file');
        if (!$file) {
            return $this->json(['error' => $this->trans('No file uploaded')], Response::HTTP_BAD_REQUEST);
        }

        $transportType = $request->request->get('transportType');
        $effectiveDate = $request->request->get('effectiveDate');
        $expiryDate    = $request->request->get('expiryDate');

        if (!$transportType || !$effectiveDate || !$expiryDate) {
            return $this->json(
                ['error' => $this->trans('transportType, effectiveDate and expiryDate are required')],
                Response::HTTP_BAD_REQUEST
            );
        }

        try {
            $job = $this->importService->parseAndPreview(
                $file,
                $transportType,
                $request->request->get('providerId') ? (int) $request->request->get('providerId') : null,
                $request->request->get('currency', 'USD'),
                $effectiveDate,
                $expiryDate,
                $this->getUser()
            );
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json($this->serializer->normalize($job, null, ['groups' => ['list']]));
    }

    #[Route('/{id}', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        $job = $this->jobRepository->find($id);
        if (!$job) {
            return $this->json(['error' => $this->trans('Not found')], Response::HTTP_NOT_FOUND);
        }

        $rows = $this->rowRepository->findBy(['importJob' => $job], ['rowNumber' => 'ASC']);
        $data = $this->serializer->normalize($job, null, ['groups' => ['list']]);
        $data['rows'] = $this->serializer->normalize($rows, null, ['groups' => ['list']]);

        return $this->json($data);
    }

    #[Route('/{id}/approve', methods: ['POST'])]
    public function approve(int $id): JsonResponse
    {
        $job = $this->jobRepository->find($id);
        if (!$job) {
            return $this->json(['error' => $this->trans('Not found')], Response::HTTP_NOT_FOUND);
        }

        try {
            $this->importService->approve($job, $this->getUser());
        } catch (\LogicException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json($this->serializer->normalize($job, null, ['groups' => ['list']]));
    }

    #[Route('/{id}/rollback', methods: ['POST'])]
    public function rollback(int $id): JsonResponse
    {
        $job = $this->jobRepository->find($id);
        if (!$job) {
            return $this->json(['error' => $this->trans('Not found')], Response::HTTP_NOT_FOUND);
        }

        try {
            $this->importService->rollback($job, $this->getUser());
        } catch (\LogicException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json($this->serializer->normalize($job, null, ['groups' => ['list']]));
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
}
