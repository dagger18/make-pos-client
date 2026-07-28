<?php
namespace App\Module\Tax\Controller;

use App\Module\Core\Controller\CrudController;

use App\Module\Tax\Entity\HsCode;
use App\Misc\Traits\Controller\DeleteActionTrait;
use App\Misc\Traits\Controller\GetActionTrait;
use App\Misc\Traits\Controller\PostActionTrait;
use App\Misc\Traits\Controller\PutActionTrait;
use App\Module\Tax\Repository\DutyRateRepository;
use App\Module\Tax\Repository\HsCodeRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Misc\Attribute\AppModule;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/hs-code')]
#[IsGranted('ROLE_USER')]
#[AppModule('tax')]
class HsCodeController extends CrudController
{
    use GetActionTrait;
    use PostActionTrait;
    use PutActionTrait;
    use DeleteActionTrait;

    #[Route('/search', methods: ['GET'])]
    public function search(Request $request, HsCodeRepository $repo): JsonResponse
    {
        $q = trim($request->query->get('q', ''));
        $limit = min((int) $request->query->get('limit', 20), 100);

        if (strlen($q) < 1) {
            return $this->json(['list' => []]);
        }

        $results = array_map(fn($h) => [
            'id'          => $h->getId(),
            'code'        => $h->getCode(),
            'description' => $h->getDescription(),
        ], $repo->createQueryBuilder('h')
            ->where('h.code LIKE :q OR h.description LIKE :q')
            ->setParameter('q', '%' . $q . '%')
            ->andWhere('h.isActive = true')
            ->orderBy('h.code', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()->getResult());

        return $this->json(['list' => $results]);
    }

    #[Route('/browse/{parentId}', methods: ['GET'])]
    public function browse(int $parentId, HsCodeRepository $repo): JsonResponse
    {
        $parent = $parentId > 0 ? $repo->find($parentId) : null;

        $qb = $repo->createQueryBuilder('h')
            ->andWhere('h.isActive = true')
            ->orderBy('h.code', 'ASC');

        if ($parentId > 0) {
            $qb->where('h.parent = :parent')->setParameter('parent', $parent);
        } else {
            $qb->where('h.parent IS NULL');
        }

        $results = array_map(fn($h) => [
            'id'          => $h->getId(),
            'code'        => $h->getCode(),
            'description' => $h->getDescription(),
            'level'       => $h->getLevel(),
            'digits'      => $h->getDigits(),
        ], $qb->getQuery()->getResult());

        return $this->json([
            'list'   => $results,
            'parent' => $parent ? ['id' => $parent->getId(), 'code' => $parent->getCode(), 'description' => $parent->getDescription()] : null,
        ]);
    }

    #[Route('/calculate-duty', methods: ['POST'])]
    public function calculateDuty(Request $request, HsCodeRepository $hsRepo, DutyRateRepository $dutyRepo): JsonResponse
    {
        $body = json_decode($request->getContent(), true) ?? [];
        $hsCodeId      = (int) ($body['hsCodeId'] ?? 0);
        $importCountry = $body['importCountry'] ?? null;
        $exportCountry = $body['exportCountry'] ?? null;
        $customsValue  = (float) ($body['customsValue'] ?? 0);

        if (!$hsCodeId) {
            return $this->json(['error' => $this->trans('hsCodeId is required.')], Response::HTTP_BAD_REQUEST);
        }

        $hsCode = $hsRepo->find($hsCodeId);
        if (!$hsCode) {
            return $this->json(['error' => $this->trans('HS code not found.')], Response::HTTP_NOT_FOUND);
        }

        $qb = $dutyRepo->createQueryBuilder('d')->where('d.hsCode = :hs')->setParameter('hs', $hsCode);
        if ($importCountry) {
            $qb->andWhere('d.importCountry = :ic OR d.importCountry IS NULL')->setParameter('ic', $importCountry);
        }
        if ($exportCountry) {
            $qb->andWhere('d.exportCountry = :ec OR d.exportCountry IS NULL')->setParameter('ec', $exportCountry);
        }

        $breakdown = array_map(function ($rate) use ($customsValue) {
            $dutyAmt   = $rate->getDutyRate()   !== null ? round((float) $rate->getDutyRate()   / 100 * $customsValue, 2) : null;
            $vatAmt    = $rate->getVatRate()    !== null ? round((float) $rate->getVatRate()    / 100 * ($customsValue + ($dutyAmt ?? 0)), 2) : null;
            $exciseAmt = $rate->getExciseRate() !== null ? round((float) $rate->getExciseRate() / 100 * $customsValue, 2) : null;
            return [
                'rateType'      => $rate->getRateType(),
                'ftaName'       => $rate->getFtaName(),
                'importCountry' => $rate->getImportCountry(),
                'exportCountry' => $rate->getExportCountry(),
                'dutyRate'      => $rate->getDutyRate(),
                'vatRate'       => $rate->getVatRate(),
                'exciseRate'    => $rate->getExciseRate(),
                'dutyAmount'    => $dutyAmt,
                'vatAmount'     => $vatAmt,
                'exciseAmount'  => $exciseAmt,
                'totalAmount'   => round(($dutyAmt ?? 0) + ($vatAmt ?? 0) + ($exciseAmt ?? 0), 2),
            ];
        }, $qb->getQuery()->getResult());

        return $this->json([
            'hsCode'        => ['id' => $hsCode->getId(), 'code' => $hsCode->getCode(), 'description' => $hsCode->getDescription()],
            'customsValue'  => $customsValue,
            'importCountry' => $importCountry,
            'exportCountry' => $exportCountry,
            'breakdown'     => $breakdown,
        ]);
    }
}
