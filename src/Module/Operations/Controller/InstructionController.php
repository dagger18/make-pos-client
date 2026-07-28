<?php
namespace App\Module\Operations\Controller;

use App\Module\Core\Controller\CrudController;

use App\Misc\Traits\Controller\DeleteActionTrait;
use App\Misc\Traits\Controller\GetActionTrait;
use App\Misc\Traits\Controller\PdfActionTrait;
use App\Misc\Traits\Controller\PostActionTrait;
use App\Misc\Traits\Controller\PutActionTrait;
use App\Resolver\CrudEntityValueResolver;
use App\Module\Core\Service\MediaService;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Misc\Attribute\AppModule;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Translation\LocaleSwitcher;

#[Route('/instruction')]
#[IsGranted('ROLE_USER')]
#[AppModule('operations')]
class InstructionController extends CrudController
{
    use GetActionTrait;
    use PostActionTrait;
    use PutActionTrait;
    use DeleteActionTrait;
    use PdfActionTrait;

    #[Route('/hawb-pdf/{language}/{id}', methods: ['GET'])]
    #[IsGranted('GET', 'entity')]
    public function hawbPdf(
        #[MapEntity(resolver: CrudEntityValueResolver::class)] $entity,
        string $language, int $id,
        Request $request,
        MediaService $mediaService,
        RequestStack $requestStack
    ): StreamedResponse {
        if (!$entity) throw $this->createNotFoundException('No entity found for id ' . $id);
        $response = new StreamedResponse();
        $data = $this->service->hawbPdfData($entity, $request, $language);
        $data['renderMode'] = 'pdf';
        $response->setCallback(function () use ($mediaService, $entity, $request, $requestStack, $data, $language): void {
            $requestStack->push($request);
            date_default_timezone_set($request->query->get('timezone'));
            $mediaService->generatePdf($entity, $data, $language);
        });
        return $response;
    }

    #[Route('/hawb-pdf-preview/{language}/{id}', methods: ['GET'])]
    #[IsGranted('GET', 'entity')]
    public function hawbPdfPreview(
        #[MapEntity(resolver: CrudEntityValueResolver::class)] $entity,
        string $language, int $id,
        Request $request,
        LocaleSwitcher $localeSwitcher
    ): Response {
        if (!$entity) throw $this->createNotFoundException('No entity found for id ' . $id);
        $data = $this->service->hawbPdfData($entity, $request, $language);
        date_default_timezone_set($request->query->get('timezone'));
        $data['renderMode'] = 'preview';
        $localeSwitcher->setLocale($language);
        $view = $this->renderView($data['template'], $data);
        $localeSwitcher->reset();
        date_default_timezone_set('UTC');
        return new Response($view);
    }

    #[Route('/packing-list-pdf/{language}/{id}', methods: ['GET'])]
    #[IsGranted('GET', 'entity')]
    public function packingListPdf(
        #[MapEntity(resolver: CrudEntityValueResolver::class)] $entity,
        string $language, int $id,
        Request $request,
        MediaService $mediaService,
        RequestStack $requestStack
    ): StreamedResponse {
        if (!$entity) throw $this->createNotFoundException('No entity found for id ' . $id);
        $response = new StreamedResponse();
        $data = $this->service->packingListPdfData($entity, $request, $language);
        $data['renderMode'] = 'pdf';
        $response->setCallback(function () use ($mediaService, $entity, $request, $requestStack, $data, $language): void {
            $requestStack->push($request);
            date_default_timezone_set($request->query->get('timezone'));
            $mediaService->generatePdf($entity, $data, $language);
        });
        return $response;
    }

    #[Route('/packing-list-pdf-preview/{language}/{id}', methods: ['GET'])]
    #[IsGranted('GET', 'entity')]
    public function packingListPdfPreview(
        #[MapEntity(resolver: CrudEntityValueResolver::class)] $entity,
        string $language, int $id,
        Request $request,
        LocaleSwitcher $localeSwitcher
    ): Response {
        if (!$entity) throw $this->createNotFoundException('No entity found for id ' . $id);
        $data = $this->service->packingListPdfData($entity, $request, $language);
        date_default_timezone_set($request->query->get('timezone'));
        $data['renderMode'] = 'preview';
        $localeSwitcher->setLocale($language);
        $view = $this->renderView($data['template'], $data);
        $localeSwitcher->reset();
        date_default_timezone_set('UTC');
        return new Response($view);
    }
}
