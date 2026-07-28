<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Resolver;

use ReflectionClass;
use App\Module\Core\Entity\SubEntity;
use App\Module\Core\Enum\Magnum;
use Psr\Log\LoggerInterface;
use App\Module\Core\Service\RequestService;
use App\Module\Core\Enum\RequestMethod;
use Doctrine\ORM\PersistentCollection;
use App\Misc\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelEvents;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Contracts\Service\ServiceCollectionInterface;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Psr\Container\ContainerInterface as PsrContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use App\Serializer\Denormalizer\CollectionEditableEntityDenormalizer;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Component\Serializer\Exception\UnsupportedFormatException;
use Symfony\Component\Serializer\Exception\PartialDenormalizationException;

/**
 * @author Konstantin Myakshin <molodchick@gmail.com>
 *
 * @final
 */
class CrudRequestPayloadValueResolver implements ValueResolverInterface, EventSubscriberInterface
{
    /**
     * @see \Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer::DISABLE_TYPE_ENFORCEMENT
     * @see DenormalizerInterface::COLLECT_DENORMALIZATION_ERRORS
     */
    private const CONTEXT_DENORMALIZE = [
        'disable_type_enforcement' => true,
        'collect_denormalization_errors' => true,
    ];

    /**
     * @see DenormalizerInterface::COLLECT_DENORMALIZATION_ERRORS
     */
    private const CONTEXT_DESERIALIZE = [
        'disable_type_enforcement' => true,
        'collect_denormalization_errors' => true,
    ];

    public function __construct(
        private readonly SerializerInterface&DenormalizerInterface $serializer,
        private readonly ?ValidatorInterface $validator,
        private readonly ?TranslatorInterface $translator,
        private readonly ServiceCollectionInterface $serviceLocator,
        private readonly LoggerInterface $logger,
        private readonly CollectionEditableEntityDenormalizer $collectionEditableEntityDenormalizer,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER_ARGUMENTS => 'onKernelControllerArguments',
        ];
    }

    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $attribute = $argument->getAttributesOfType(MapQueryString::class, ArgumentMetadata::IS_INSTANCEOF)[0]
            ?? $argument->getAttributesOfType(MapRequestPayload::class, ArgumentMetadata::IS_INSTANCEOF)[0]
            ?? null;

        if (!$attribute) {
            return [];
        }
        
        if ($argument->isVariadic()) {
            throw new \LogicException(sprintf('Mapping variadic argument "$%s" is not supported.', $argument->getName()));
        }

        if (isset($attribute->serializationContext['entityClassName'])) {
            $entityClassName = $attribute->serializationContext['entityClassName'];
            $entityName = $attribute->serializationContext['entityName']
                ?? (new \ReflectionClass($entityClassName))->getShortName();
        } else {
            $controllerStr = $request->attributes->get('_controller');
            preg_match('/App\\\\Module\\\\([^\\\\]+)\\\\Controller\\\\([a-zA-Z]+)Controller/', $controllerStr, $match);

            if (isset($match[1], $match[2])) {
                $module = $match[1];
                $entityName = $attribute->serializationContext['entityName'] ?? $match[2];
                $entityClassName = 'App\\Module\\' . $module . '\\Entity\\' . $entityName;
            } else {
                preg_match('/\\\\([a-zA-Z]+)Controller/', $controllerStr, $legacyMatch);
                $entityName = $attribute->serializationContext['entityName'] ?? ($legacyMatch[1] ?? '');
                $entityClassName = 'App\\Entity\\' . $entityName;
            }
        }

        $reflectEntityClass= new ReflectionClass($entityClassName);

        $attribute->metadata = new ArgumentMetadata(
            'entity', $entityClassName, 
            false, false, null, false, 
            [
              'isSubEntity' => $reflectEntityClass->isSubclassOf( SubEntity::class ),
              'entityName' => $entityName
            ]
        );
        return [$attribute];
    }

    public function onKernelControllerArguments(ControllerArgumentsEvent $event): void
    {
        $arguments = $event->getArguments();

        foreach ($arguments as $i => $argument) {
            if (!($argument instanceof MapRequestPayload)) {
                continue;
            }
            $validationFailedCode = Response::HTTP_UNPROCESSABLE_ENTITY;
            $request = $event->getRequest();

            if (!$type = $argument->metadata->getType()) {
                throw new \LogicException(sprintf('Could not resolve the "$%s" controller argument: argument should be typed.', $argument->metadata->getName()));
            }
            try {
                $payload = $this->mapRequestPayload($request, $type, $argument);
            } catch (PartialDenormalizationException $e) {
                throw new HttpException($validationFailedCode, implode("\n", array_map(static fn ($e) => $e->getMessage(), $e->getErrors())), $e);
            }
            if ($this->validator) {
                $violations = new ConstraintViolationList();
                $this->logger->info('violations', [$violations]);
                if (null !== $payload) {
                    $violations->addAll($this->validator->validate($payload, null, $argument->validationGroups ?? null));
                }
                if (\count($violations)) {
                    $messages = [];
                    foreach ($violations as $violation) {
                        $messages[$violation->getPropertyPath()] = $violation->getMessage();
                    }
                    throw new BadRequestException(
                        BadRequestException::WRONG_INPUT,
                        null,
                        $type, null, $messages
                    );
                }
            }

            if (null === $payload) {
                $payload = match (true) {
                    $argument->metadata->hasDefaultValue() => $argument->metadata->getDefaultValue(),
                    $argument->metadata->isNullable() => null,
                    default => throw new HttpException($validationFailedCode)
                };
            }

            $arguments[$i] = $payload;
        }

        $event->setArguments($arguments);
    }

    private function mapRequestPayload(Request $request, string $type, MapRequestPayload $attribute): ?object
    {
        if (null === $format = $request->getContentTypeFormat()) {
            throw new HttpException(Response::HTTP_UNSUPPORTED_MEDIA_TYPE, $this->translator->trans('Unsupported format.'));
        }

        if ($attribute->acceptFormat && !\in_array($format, (array) $attribute->acceptFormat, true)) {
            throw new HttpException(Response::HTTP_UNSUPPORTED_MEDIA_TYPE, sprintf($this->translator->trans('Unsupported format, expects "%s", but "%s" given.'), implode('", "', (array) $attribute->acceptFormat), $format));
        }
        $context = array_merge($attribute->serializationContext ?? [], self::CONTEXT_DESERIALIZE);

        // here you find the group for deserialize
        /** @var RequestService $requestService */
        $requestService = $this->serviceLocator->get('App\Service\RequestService');
        //$realmPart = $requestService->getRealm();
        //$actionPart = $requestService->getAction();
        //$context['groups'] = [$realmPart . $actionPart];
        // you should rely on serial context here to define denormalizer group 

        $data = $this->sanitizeRequestData($request->request->all(), $type);
        $isUpdating = in_array(RequestMethod::from($request->getMethod()), RequestMethod::updateMethodList());
        if ($isUpdating) {
            unset($data['id'], $data['createdDate'], $data['updatedDate']);
        }
        if($isUpdating && $request->attributes->has('id')) {
            $context += [
                AbstractNormalizer::OBJECT_TO_POPULATE 
                    => $this->getCurrentEntity(
                        $request->attributes->get('id'), 
                        $attribute->metadata->getAttributes()['entityName'],
                        $request
                    )
            ];
        }
        // upload file and add to data
        if($request->files) {
            $this->serviceLocator->get('App\Service\MediaService')->handleFileUpload($data, $request->files, $type);
        }
        $map = Magnum::ENTITY_COLLECTION_UPDATABLE_MAP();
        if(isset($map[$type]) && $isUpdating) {
            //header('Access-Control-Allow-Origin: *');dd('heere');
            return $this
                ->collectionEditableEntityDenormalizer
                ->denormalize(
                    $data, $type, null, 
                    [...$context, 'collectionProperties' => $map[$type]]
                );
        } 
        //dd($data, $type, $context);
        return $this->serializer->denormalize($data, $type, null, $context);
        
    }

    private function getCurrentEntity($id, $entityName, $request) {
        // check ownership here
        
        $entity = $this->serviceLocator->get('App\Service\\' . $entityName . 'Service')->repository->find($id);
        if(is_null($entity))
            throw new NotFoundHttpException($this->translator->trans('No %entityName% found for id %id%', ['%entityName%' => $entityName, '%id%' => $id]));

        $requestService = $this->serviceLocator->get('App\Service\RequestService');

        $ownershipFields = $requestService->getOwnershipFields($request);
        // no need to check, return object
        if(empty($ownershipFields)) return $entity;
        // check every fields
        forEach(explode(',', $ownershipFields) as $ownershipField) {
          if(method_exists($entity, 'get' . ucfirst($ownershipField))
            && $entity->{'get' . ucfirst($ownershipField)}() === $requestService->getUser()
            ) {
              return $entity;
          } 
        }
        throw new NotFoundHttpException(sprintf($this->translator->trans('%s not found'), $entityName ?? 'Entity'));
    }

    private function isCollectionType(?\ReflectionType $type): bool
    {
        if (!($type instanceof \ReflectionNamedType)) {
          $this->logger->info('ReflectionNamedType', ['type' => $type]);
            return false;
        }
        $name = $type->getName();
        if ($name === 'array') {
            return true;
        }
        return is_a($name, \Doctrine\Common\Collections\Collection::class, true);
    }

    private function sanitizeRequestData(array $data, string $type): array
    {
        $ref = new ReflectionClass($type);
        foreach ($ref->getProperties() as $prop) {
            $propType = $prop->getType();
            $name = $prop->getName();
            if (!array_key_exists($name, $data)) {
                continue;
            }
            if ($propType instanceof \ReflectionNamedType) {
                // "" → null for any nullable non-string type (int, float, object relations, etc.)
                if ($propType->allowsNull() && $propType->getName() !== 'string' && $data[$name] === '') {
                    $data[$name] = null;
                }
            } elseif ($propType instanceof \ReflectionUnionType || $propType instanceof \ReflectionIntersectionType) {
                // skip union/intersection types
            }
            // 'isEmpty' sentinel → [] for collections, null for everything else
            if ($data[$name] === 'isEmpty') {
                $this->logger->info('Sanitize request data: "isEmpty" sentinel found', ['property' => $name, 'type' => $type, 'propType' => $propType?->getName()]);
                $data[$name] = $this->isCollectionType($propType) ? [] : null;
            }
        }
        return $data;
    }

}
