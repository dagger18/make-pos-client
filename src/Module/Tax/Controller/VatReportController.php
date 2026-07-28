<?php
namespace App\Module\Tax\Controller;

use App\Module\Tax\Repository\VatReportRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use App\Misc\Attribute\AppModule;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/report/vat')]
#[IsGranted('ROLE_USER')]
#[AppModule('tax')]
class VatReportController extends AbstractController
{
    public function __construct(private readonly VatReportRepository $repo) {}

    #[Route('', methods: ['GET'])]
    public function report(Request $request): JsonResponse
    {
        $from = $request->query->get('from', date('Y-m-01'));
        $to   = $request->query->get('to', date('Y-m-d'));

        $outputTax   = $this->repo->getOutputTax($from, $to);
        $inputTax    = $this->repo->getInputTax($from, $to);
        $withholding = $this->repo->getWithholdingTax($from, $to);

        $totalOutput = array_sum(array_column($outputTax, 'tax_amount'));
        $totalInput  = array_sum(array_column($inputTax, 'tax_amount'));

        return $this->json([
            'outputTax'      => $outputTax,
            'inputTax'       => $inputTax,
            'withholdingTax' => $withholding,
            'netVatPayable'  => round((float)$totalOutput - (float)$totalInput, 6),
        ]);
    }
}
