<?php

namespace App\Misc\Doctrine\Type;

use Psr\Log\LoggerInterface;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Serializer\Debug\TraceableSerializer;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Contracts\Service\Attribute\Required;

class BaseCollectionType extends Type implements ServiceTypeInterface
{
    private static ?SerializerInterface $sharedSerializer = null;
    private static ?LoggerInterface $sharedLogger = null;

    /** @var TraceableSerializer $serializer **/
    protected $serializer;
    protected $logger;

    #[Required]
    public function setService(SerializerInterface $serializer, LoggerInterface $logger)
    {
        self::$sharedSerializer = $serializer;
        self::$sharedLogger = $logger;
        $this->serializer = $serializer;
        $this->logger = $logger;
    }

    private function getSerializer(): SerializerInterface
    {
        return $this->serializer ?? self::$sharedSerializer
            ?? throw new \RuntimeException(static::class . ': serializer not injected.');
    }
    protected $properties = []; 
    protected $entityClass = '';
    public function getName() {

    }
    public function getSQLDeclaration(array $column, AbstractPlatform $platform)
    {
        return $platform->getJsonTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform)
    {
        if ($value === null) {
            return null;
        }
        try {
            $this->logger?->info('normalized collection before' , [$value]);
            $collection = $this->getSerializer()->normalize($value, null,[AbstractObjectNormalizer::SKIP_UNINITIALIZED_VALUES => false]);
            $this->logger?->info('normalized collection' , $collection);
            $array = [];
            forEach($collection as $item) {
                // todo: abide for your sin :)))
                $object = [];
                forEach($this->properties as $property) {
                    $object[] = $item[$property];
                } 
                $array[] = $object;
            }
            return json_encode($array, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
        } catch (\JsonException $e) {
            throw ConversionException::conversionFailedSerialization($value, 'json', $e->getMessage(), $e);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_resource($value)) {
            $value = stream_get_contents($value);
        }
        $this->logger?->info('database to php value before parse' , [$value]);
        try {
            $array =  json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw ConversionException::conversionFailed($value, $this->getName(), $e);
        }
        $this->logger?->info('collection' ,  $array);
        
        $collection = [];
        forEach($array as $index=>$item) {
            // todo: abide for your sin :)))
            $item = array_combine($this->properties, $item);
            $entity = $this->getSerializer()->deserialize(
                json_encode($item, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION), $this->entityClass, 'json', 
                [AbstractObjectNormalizer::DISABLE_TYPE_ENFORCEMENT => true]
            );
            $collection[] = $entity;
            
        }
        $this->logger?->info('database to php value' , [$collection]);
        return $collection;
    }
}