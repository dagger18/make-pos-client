<?php

namespace App\Module\Core\Service;

use App\Module\Core\Entity\User;
use App\Module\Core\Service\CommonService;
use App\Module\Finance\Entity\EbitNote;
use App\Module\Reporting\Enum\DatasetRowType;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\QueryBuilder;
use League\Flysystem\FilesystemOperator;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Psr\Container\ContainerInterface as PsrContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\TraceableAdapter;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\UrlHelper;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Service\ServiceCollectionInterface;

class BaseService
{
    /**
     * @param TraceableAdapter $appCache
     * @param TraceableAdapter $codeCache
     * @param Serializer $serializer
    */
    public function __construct(
        protected ContainerInterface $container,
        public CommonService $commonService,
        protected ValidatorInterface $validator,
        protected TokenStorageInterface $tokenStorage,
        protected ParameterBagInterface $params,
        protected UrlHelper $urlHelper,
        protected CacheInterface $appCache,
        protected CacheInterface $codeCache,
        public ServiceCollectionInterface $serviceLocator,
        protected LoggerInterface $logger,
        protected FilesystemOperator $mediaStorage,
        protected SerializerInterface $serializer,
        )
    {
    }

    public function reflectFromParent($parent)
    {
        $this->container = $parent->container;
        $this->commonService = $parent->commonService;
        $this->validator = $parent->validator;
        $this->tokenStorage = $parent->tokenStorage;
        $this->mediaStorage = $parent->mediaStorage;
        $this->appCache = $parent->appCache;
        $this->codeCache = $parent->codeCache;
        $this->params = $parent->params;
        $this->urlHelper = $parent->urlHelper;
        $this->serviceLocator = $parent->serviceLocator;
        $this->logger = $parent->logger;
        $this->serializer = $parent->serializer;
    }

    public function getContainer()
    {
        return $this->container;
    }

    /**
     * @return EntityManagerInterface
     */
    public function getEntityManager() {
        if(!isset($this->repository)) {
            return $this->userRepo->getEntityManager();
        }
        return $this->repository->getEntityManager();
    }

    public function __get($propertyName) {
        preg_match('/([a-zA-Z0-9]+)Service/i', $propertyName, $serviceMatches);
        if (count($serviceMatches) > 0) {
            return $this
                ->serviceLocator
                ->get('App\Service\\' . ucfirst($serviceMatches[1]) . 'Service');
        }

        preg_match('/([a-zA-Z0-9]+)Repo/i', $propertyName, $repositoryMatches);
        if (count($repositoryMatches) > 0) {
            return $this
                ->serviceLocator
                ->get('App\Service\\' . ucfirst($repositoryMatches[1]) . 'Service')
                ->repository;
        }

        trigger_error('Could not found property ' . $propertyName . ' in ' . $this->commonService->getClassName($this), E_USER_ERROR);
    }

    public function newEntity()
    {
        $entityName = $this->commonService->getClassName($this, true);
        $entityClass = 'App\\Entity\\' . $entityName;
        return new $entityClass();
    }

    public function getToken()
    {
        $token = $this->tokenStorage->getToken();
        if (is_null($token) || $token->getUser() === 'anon.') return null;
        return $token;
    }
    
    public function getUser(): ?User
    {
        $token = $this->getToken();
        if (is_null($token)) return null;
        return $token->getUser();
    }
    
    public function isLoggedIn() {
        return $this->getUser();
    }

    public function getClassProperties($className)
    {
        $reflectionClass = new \ReflectionClass($className);
        $properties = [];
        foreach ($reflectionClass->getProperties() as $property) {
            $properties[] = $property->getName();

        }
        return $properties;
    }

    public function getRootDir()
    {
        return $this->params->get('kernel.project_dir');
    }

    public function getDomain(): string
    {
        $appBaseUrl = $this->params->get('app_base_url');
        if (!empty($appBaseUrl)) {
            return rtrim($appBaseUrl, '/');
        }
        $token      = $this->params->get('database_token');
        $baseDomain = $this->params->get('base_domain');
        return 'https://' . $token . '.' . $baseDomain;
    }

    public function normalizeChangeSet(array $changeSet, $entity) {
        $entityName = $this->commonService->getClassName($entity, true);
        $metadata = $this->getEntityManager()->getClassMetadata('App\Entity\\' . $entityName);
        $typeMap = [];
        foreach ($metadata->fieldMappings as $fieldData) {
            $typeMap[$fieldData['fieldName']] = $fieldData['type'];
        }
        foreach ($metadata->associationMappings as $associationMapping) {
            $typeMap[$associationMapping['fieldName']] = $associationMapping['type'];
        }
        foreach ($changeSet as $property => list($oldValue, $newValue)) {
            if (!isset($typeMap[$property])) continue;
            if ($typeMap[$property] == ClassMetadata::MANY_TO_ONE) {
                if(!is_null($oldValue)) {
                    $oldValue = $oldValue->getId();
                }
                if(!is_null($newValue)) {
                    $newValue = $newValue->getId();
                }
                $changeSet[$property] = [$oldValue, $newValue];
            }
            // $this->logger->info('changeset ' . $property, [$oldValue, $newValue, $oldValue == $newValue, $oldValue === $newValue, serialize($oldValue) == serialize($newValue)]);
            if($oldValue == $newValue) {
                unset($changeSet[$property]);
            }
        }
        return $changeSet;
    }

    public function pdfData(mixed $entity, \Symfony\Component\HttpFoundation\Request $request, string $language): array
    {
        throw new \LogicException(get_class($this) . ' must implement pdfData().');
    }

    /*
    public function export($requestData, Array|QueryBuilder $queryBuilder) {
        //$this->container->get('profiler')->disable();
        if(isset($requestData['exportType'])) {
            return $this->{$requestData['exportType']}($requestData, $queryBuilder);
        }
        $requestData['columns'] = ['', ...$requestData['columns']];
        $rows = [...$queryBuilder['data'], $queryBuilder['total']];
        //header('Access-Control-Allow-Origin: *');dd($rows, $requestData['title']);
        
        $spreadsheet = new Spreadsheet();
        $activeWorksheet = $spreadsheet->getActiveSheet();
        forEach($requestData['title'] as $columnIndex => $title) {
            $activeWorksheet->setCellValue([$columnIndex + 1, 1], urldecode($title));
            $activeWorksheet->getColumnDimensionByColumn($columnIndex + 1)->setAutoSize(true);
        }
        forEach($rows as $rowIndex => $entity) {
            $object = $entity;
            unset($entity);
            forEach($requestData['columns'] as $columnIndex => $column) {
                $activeWorksheet->setCellValue(
                    [$columnIndex + 1, $rowIndex + 2], 
                    $object[$columnIndex]
                );
                
            }
            unset($object);
        }
        unset($rows);
        $writer = new Xlsx($spreadsheet);
        //$writer->save('lol.xlsx');
        //return new JsonResponse(['status' => 'success']);
        $response =  new StreamedResponse(
            function () use ($writer) {
                $writer->save('php://output');
            }
        );
        $exportFileName = $requestData['exportFileName'] ?? 'ExportReport';
        $response->headers->set('Content-Type', 'application/vnd.ms-excel');
        $response->headers->set('Content-Disposition', 'attachment;filename="' . $exportFileName . '.xlsx"');
        $response->headers->set('Cache-Control','max-age=0');
        return $response;
    }
        */
}
