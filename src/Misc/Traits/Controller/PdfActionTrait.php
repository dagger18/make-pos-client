<?php
namespace App\Misc\Traits\Controller;

use App\Misc\Enum\CapacityType;
use App\Module\Core\Service\CapacityService;
use App\Resolver\CrudEntityValueResolver;
use App\Module\Core\Service\MediaService;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Translation\LocaleSwitcher;

/**
 * @property \App\Repository\BaseRepository $repository
 * @property \App\Service\MediaService $mediaService
 * @property \App\Service\BaseService $service
 * @property \League\Flysystem\FilesystemOperator $mediaStorage
 * @property \Symfony\Component\HttpFoundation\RequestStack $requestStack
 * @property \Symfony\Component\Translation\LocaleSwitcher $localeSwitcher
 * @method string renderView(string $view, array $parameters = [])
 * @method \Symfony\Component\HttpKernel\Exception\NotFoundHttpException createNotFoundException(string $message = 'Not Found', ?\Throwable $previous = null)
 */
trait PdfActionTrait
{

    #[Route('/pdf/{language}/{id}', methods: ['GET'])]
    #[IsGranted('GET', 'entity')]
    public function pdf(
        #[MapEntity(
            resolver: CrudEntityValueResolver::class
        )]
        $entity, string $language, int $id,
        Request $request,
        MediaService $mediaService,
        RequestStack $requestStack,
        CapacityService $capacityService
    ): StreamedResponse {
        if (!$entity) {
            throw $this->createNotFoundException(
                'No entity found for id '. $id
            );
        }
        $capacityService->assertAllowed(CapacityType::DocumentOperations);
        $pdf = null;
        /*
        forEach($entity->getPdfs() as $media) {
            if($media->getLanguage() === $language) {
                $pdf = $media;
            }
        }
            */
        if(is_null($pdf)
            || $pdf->getCreatedDate()->modify("+5 seconds") < $entity->getUpdatedDate()
        ) {
            $response = new StreamedResponse();
            $data = $this->service->pdfData($entity, $request, $language);
            $data['renderMode'] = 'pdf';
            $response->setCallback(
                function () use (
                    $mediaService, $entity,
                    $request, $requestStack, $data, $language,
                    $capacityService
                ) : void {
                    $requestStack->push($request);
                    date_default_timezone_set($request->query->get('timezone'));
                    $mediaService->generatePdf($entity, $data, $language);
                    $capacityService->recordUsage(CapacityType::DocumentOperations);
                }
            );
            return $response;
        }
        $stream = $this->mediaStorage->readStream($pdf->getPath());
        return new StreamedResponse(function () use ($stream) {
            fpassthru($stream);
            exit();
        }, 200, [
            'Content-Transfer-Encoding' => 'binary',
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $pdf->getName())
        ]);
    }

    #[Route('/pdf-preview/{language}/{id}', methods: ['GET'])]
    #[IsGranted('GET', 'entity')]
    public function pdfPreview(
        #[MapEntity(
            resolver: CrudEntityValueResolver::class
        )]
        $entity, $language, int $id,
        Request $request,
        LocaleSwitcher $localeSwitcher
    ): Response {
        if (!$entity) {
            throw $this->createNotFoundException(
                'No entity found for id '. $id
            );
        }
        $data = $this->service->pdfData($entity, $request, $language);
        date_default_timezone_set($request->query->get('timezone'));
        //header('Access-Control-Allow-Origin: *');dd($data);
        $data['renderMode'] = 'preview';
        $localeSwitcher->setLocale($language);
        $view = $this->renderView($data['template'], $data);
        $localeSwitcher->reset();
        date_default_timezone_set('UTC');
        
        return new Response($view);
    }

}