<?php
namespace App\Serializer\Denormalizer;

use Psr\Log\LoggerInterface;
use App\Entity\CollectionEditableEntity;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\Service\ServiceCollectionInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;

class CollectionEditableEntityDenormalizer implements DenormalizerInterface, DenormalizerAwareInterface
{
    use DenormalizerAwareTrait;
    public function __construct(
        protected ServiceCollectionInterface $serviceLocator,
        protected LoggerInterface $logger,
    ) {
    }

    public function denormalize(mixed $data, string $type, string $format = null, array $context = []): mixed {
        $collections = [];
        forEach($context['collectionProperties'] as $prop => $config) {
            if (!array_key_exists($prop, $data)) {
                continue;
            }
            $collections[$prop] = [];
            $service = $this->serviceLocator->get('App\Service\\' . $config['childName'] . 'Service');
            forEach($data[$prop] as $row) {
                $rowNoId = $row;
                unset($rowNoId['id']);
                forEach($rowNoId as $column => &$columnValue) {
                    if($columnValue === 'isEmpty') {
                        $columnValue = null;
                    }
                    if($column === 'charge' && empty($columnValue)) {
                        $columnValue = null;
                    }
                }
                $this->logger->info('row before denormalize', [$rowNoId]);
                $child = null;
                try{
                    $child = $this->denormalizer->denormalize(
                        $rowNoId, 
                        'App\Entity\\' . $config['childName'], null, 
                        isset($row['id']) 
                        ? [
                            AbstractNormalizer::OBJECT_TO_POPULATE => $service->repository->find($row['id']),
                            ObjectNormalizer::DISABLE_TYPE_ENFORCEMENT => true
                        ] 
                        : [
                            ObjectNormalizer::DISABLE_TYPE_ENFORCEMENT => true
                        ]
                    );
                } catch ( NotNormalizableValueException $error ) {
                     
                    //header('Access-Control-Allow-Origin: *');
                    //dd($error->getCurrentType(), $error->getExpectedTypes(), $error->getPath());
                    if($error->getCurrentType() === 'string'
                        && $error->getExpectedTypes()
                        && ($error->getExpectedTypes()[0] === 'int' || $error->getExpectedTypes()[0] === 'float')
                    ) {
                        $value = $row[$error->getPath()];
                        settype($value, $error->getExpectedTypes()[0]);
                        $this->logger->info('ediatable parse float', [$error, $row, $value, $error->getExpectedTypes()[0]]);
                        $child->{'set' . ucfirst($error->getPath())}($value);
                    } else {
                        throw $error;
                    }
                }
                
                if(!$child->getId() && method_exists($child, 'setCreatedBy')) {
                    $child->setCreatedBy($service->getUser());
                }
                $collections[$prop][] = $child;
            }
            
            unset($data[$prop]);
        }
        
        $isUpdating = isset($context[AbstractNormalizer::OBJECT_TO_POPULATE]);

        if ($isUpdating) {
            forEach($context['collectionProperties'] as $prop => $config) {
                if (!isset($collections[$prop])) continue;
                $oldRelations = $context[AbstractNormalizer::OBJECT_TO_POPULATE]->{'get' . ucwords($prop)}();
                $newRelations = $collections[$prop];
                $currentRelationIdList = [];
                forEach($oldRelations as $oldRelation) {
                    forEach($newRelations as $newRelation) {
                        if($newRelation->getId() == $oldRelation->getId()) {
                            $currentRelationIdList[] = $oldRelation->getId();
                            continue 2;
                        }
                    }
                    $context[AbstractNormalizer::OBJECT_TO_POPULATE]->{'remove' . $config['singular']}($oldRelation);
                }
                forEach($newRelations as $newRelation) {
                    if($newRelation->getId()) {
                        forEach($oldRelations as $oldRelation) {
                            if(in_array($newRelation->getId(), $currentRelationIdList)) {
                                continue 2;
                            }
                        }
                    }
                    $context[AbstractNormalizer::OBJECT_TO_POPULATE]->{'add' . $config['singular']}($newRelation);
                }
            }
        }

        $entity = $this->denormalizer->denormalize($data, $type, null, $context);

        if (!$isUpdating) {
            forEach($context['collectionProperties'] as $prop => $config) {
                if (!isset($collections[$prop])) continue;
                forEach($collections[$prop] as $newRelation) {
                    $entity->{'add' . $config['singular']}($newRelation);
                }
            }
        }

        return $entity;
    }
    public function getSupportedTypes(?string $format): array
    {
        return [
        ];
    }

    public function supportsDenormalization(mixed $data, string $type, string $format = null  , array $context = [] ): bool
    {
        return 
            false
        ;
    }
}