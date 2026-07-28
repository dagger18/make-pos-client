<?php

namespace App\Module\Operations\Controller;

use App\Module\Operations\Entity\DangerousGoods;
use App\Module\Core\Enum\Magnum;
use App\Module\Operations\Repository\DangerousGoodsRepository;
use App\Module\Carrier\Repository\ProviderRepository;
use App\Module\Operations\Repository\ShipmentRepository;
use App\Module\Core\Service\MediaService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Misc\Attribute\AppModule;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Translation\LocaleSwitcher;

#[Route('/shipment/{shipmentId}/dangerous-goods')]
#[IsGranted('ROLE_USER')]
#[AppModule('operations')]
class DangerousGoodsController extends AbstractController
{
    public function __construct(
        private readonly DangerousGoodsRepository $dgRepository,
        private readonly ShipmentRepository $shipmentRepository,
        private readonly ProviderRepository $providerRepository,
    ) {}

    #[Route('', methods: ['GET'])]
    public function list(int $shipmentId): JsonResponse
    {
        return $this->json(array_map(
            fn($dg) => $this->serialize($dg),
            $this->dgRepository->findByShipment($shipmentId)
        ));
    }

    #[Route('', methods: ['POST'])]
    public function create(int $shipmentId, Request $request): JsonResponse
    {
        $shipment = $this->shipmentRepository->find($shipmentId);
        if (!$shipment) throw $this->createNotFoundException();

        $body = json_decode($request->getContent(), true) ?? [];
        $dg = $this->hydrate(new DangerousGoods(), $body);
        $dg->setShipment($shipment);

        $errors = $this->validate($dg, $body);
        if ($errors) return $this->json(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->dgRepository->save($dg);
        return $this->json($this->serialize($dg), Response::HTTP_CREATED);
    }

    #[Route('/{dgId}', methods: ['PUT'])]
    public function update(int $shipmentId, int $dgId, Request $request): JsonResponse
    {
        $dg = $this->dgRepository->find($dgId);
        if (!$dg || $dg->getShipment()->getId() !== $shipmentId) throw $this->createNotFoundException();

        $body = json_decode($request->getContent(), true) ?? [];
        $this->hydrate($dg, $body);

        $errors = $this->validate($dg, $body);
        if ($errors) return $this->json(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->dgRepository->save($dg);
        return $this->json($this->serialize($dg));
    }

    #[Route('/{dgId}', methods: ['DELETE'])]
    public function delete(int $shipmentId, int $dgId): JsonResponse
    {
        $dg = $this->dgRepository->find($dgId);
        if (!$dg || $dg->getShipment()->getId() !== $shipmentId) throw $this->createNotFoundException();

        $this->dgRepository->delete($dg);
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    private function hydrate(DangerousGoods $dg, array $body): DangerousGoods
    {
        if (isset($body['imoClass']))          $dg->setImoClass($body['imoClass']);
        if (isset($body['unNumber']))          $dg->setUnNumber($body['unNumber']);
        if (array_key_exists('packingGroup', $body))  $dg->setPackingGroup($body['packingGroup'] ?: null);
        if (isset($body['properName']))        $dg->setProperName($body['properName']);
        if (array_key_exists('technicalName', $body)) $dg->setTechnicalName($body['technicalName'] ?: null);
        if (array_key_exists('netQuantity', $body))   $dg->setNetQuantity($body['netQuantity'] ?: null);
        if (array_key_exists('grossQuantity', $body)) $dg->setGrossQuantity($body['grossQuantity'] ?: null);
        if (array_key_exists('uom', $body))           $dg->setUom($body['uom'] ?: null);
        if (array_key_exists('flashPoint', $body))    $dg->setFlashPoint($body['flashPoint'] ?: null);
        if (array_key_exists('subsidiaryRisk', $body))$dg->setSubsidiaryRisk($body['subsidiaryRisk'] ?: null);
        if (isset($body['isMarinePollutant'])) $dg->setIsMarinePollutant((bool) $body['isMarinePollutant']);
        if (isset($body['isLimitedQty']))      $dg->setIsLimitedQty((bool) $body['isLimitedQty']);
        if (isset($body['isExceptedQty']))     $dg->setIsExceptedQty((bool) $body['isExceptedQty']);
        if (array_key_exists('emergencyContact', $body)) $dg->setEmergencyContact($body['emergencyContact'] ?: null);
        if (array_key_exists('msdsUrl', $body))          $dg->setMsdsUrl($body['msdsUrl'] ?: null);
        return $dg;
    }

    private function validate(DangerousGoods $dg, array $body): array
    {
        $errors = [];
        if ($dg->getImoClass() === '') $errors[] = 'IMO class is required.';
        if ($dg->getUnNumber() === '')  $errors[] = 'UN number is required.';
        if ($dg->getProperName() === '') $errors[] = 'Proper name is required.';
        if ($dg->getImoClass() === '3' && $dg->getFlashPoint() === null) {
            $errors[] = 'Flash point is required for class 3 (flammable liquids).';
        }
        return $errors;
    }

    private function serialize(DangerousGoods $dg): array
    {
        return [
            'id'               => $dg->getId(),
            'imoClass'         => $dg->getImoClass(),
            'unNumber'         => $dg->getUnNumber(),
            'packingGroup'     => $dg->getPackingGroup(),
            'properName'       => $dg->getProperName(),
            'technicalName'    => $dg->getTechnicalName(),
            'netQuantity'      => $dg->getNetQuantity(),
            'grossQuantity'    => $dg->getGrossQuantity(),
            'uom'              => $dg->getUom(),
            'flashPoint'       => $dg->getFlashPoint(),
            'subsidiaryRisk'   => $dg->getSubsidiaryRisk(),
            'isMarinePollutant'=> $dg->isMarinePollutant(),
            'isLimitedQty'     => $dg->isLimitedQty(),
            'isExceptedQty'    => $dg->isExceptedQty(),
            'emergencyContact' => $dg->getEmergencyContact(),
            'msdsUrl'          => $dg->getMsdsUrl(),
            'createdAt'        => $dg->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    #[Route('/pdf/{language}', methods: ['GET'])]
    public function pdf(
        int $shipmentId, string $language,
        Request $request,
        MediaService $mediaService,
        RequestStack $requestStack
    ): StreamedResponse {
        $shipment = $this->shipmentRepository->find($shipmentId);
        if (!$shipment) throw $this->createNotFoundException();

        $company = $this->providerRepository->find(Magnum::COMPANY_PROVIDER_ID);
        $dgList = $this->dgRepository->findByShipment($shipmentId);
        $data = [
            'company' => $company,
            'shipment' => $shipment,
            'booking' => $shipment->getBooking(),
            'dgList' => $dgList,
            'basePath' => $request->getUriForPath(''),
            'filename' => 'DG_' . $shipment->getCode() . '_' . $language . '.pdf',
            'template' => 'pdf/dangerous-goods.html.twig',
            'renderMode' => 'pdf',
        ];

        $response = new StreamedResponse();
        $response->setCallback(function () use ($mediaService, $shipment, $request, $requestStack, $data, $language): void {
            $requestStack->push($request);
            date_default_timezone_set($request->query->get('timezone', 'UTC'));
            $mediaService->generatePdf($shipment, $data, $language);
        });
        return $response;
    }

    #[Route('/pdf-preview/{language}', methods: ['GET'])]
    public function pdfPreview(
        int $shipmentId, string $language,
        Request $request,
        LocaleSwitcher $localeSwitcher
    ): Response {
        $shipment = $this->shipmentRepository->find($shipmentId);
        if (!$shipment) throw $this->createNotFoundException();

        $company = $this->providerRepository->find(Magnum::COMPANY_PROVIDER_ID);
        $dgList = $this->dgRepository->findByShipment($shipmentId);
        $data = [
            'company' => $company,
            'shipment' => $shipment,
            'booking' => $shipment->getBooking(),
            'dgList' => $dgList,
            'basePath' => $request->getUriForPath(''),
            'renderMode' => 'preview',
        ];

        date_default_timezone_set($request->query->get('timezone', 'UTC'));
        $localeSwitcher->setLocale($language);
        $view = $this->renderView('pdf/dangerous-goods.html.twig', $data);
        $localeSwitcher->reset();
        date_default_timezone_set('UTC');
        return new Response($view);
    }
}
