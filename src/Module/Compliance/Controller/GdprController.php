<?php
declare(strict_types=1);
namespace App\Module\Compliance\Controller;

use App\Misc\Attribute\AppModule;
use App\Module\Compliance\Service\GdprService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/compliance/gdpr')]
#[IsGranted('ROLE_USER')]
#[AppModule('compliance')]
class GdprController extends AbstractController
{
    public function __construct(private readonly GdprService $gdprService) {}

    #[Route('/erase', methods: ['POST'])]
    public function erase(Request $request): JsonResponse
    {
        $data   = json_decode($request->getContent(), true);
        $email  = $data['email'] ?? '';
        $ref    = $data['requestRef'] ?? 'GDPR-' . date('Ymd');

        if (!$email) {
            return $this->json(['error' => $this->trans('email is required')], 422);
        }

        $result = $this->gdprService->handleErasureRequest(
            email:        $email,
            requestRef:   $ref,
            requestorId:  isset($data['requestorId']) ? (int) $data['requestorId'] : null,
        );

        $status = $result['status'] === 'NOT_FOUND' ? 404 : 200;
        return $this->json($result, $status);
    }

    #[Route('/export/{contactId}', methods: ['GET'])]
    public function export(int $contactId): JsonResponse
    {
        $result = $this->gdprService->exportSubjectData($contactId);
        $status = $result['status'] === 'NOT_FOUND' ? 404 : 200;
        return $this->json($result, $status);
    }
}
