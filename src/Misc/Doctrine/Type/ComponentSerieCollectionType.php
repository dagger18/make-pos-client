<?php

namespace App\Misc\Doctrine\Type;

use Psr\Log\LoggerInterface;
use App\Module\Reporting\Entity\DatasetFilter;
use Doctrine\DBAL\Types\Type;
use App\Module\Core\Entity\ComponentSerie;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Symfony\Component\Serializer\SerializerInterface;
use App\Misc\Doctrine\Collection\DatasetFilterCollection;
use App\Misc\Doctrine\Collection\ComponentSerieCollection;
use Symfony\Component\Serializer\Debug\TraceableSerializer;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;

class ComponentSerieCollectionType extends Type implements ServiceTypeInterface
{
    /** @var TraceableSerializer $serializer **/
    protected $serializer;
    protected $logger;
    /**
     * We have to use setter injection since parent Type class make the constructor final
     * @required
     */

    public function setService(SerializerInterface $serializer, LoggerInterface $logger)
    {
        $this->serializer = $serializer;
        $this->logger = $logger;
        
    }
    protected $properties = [
        'type',
        'color',
        'column',
    ]; 
    public function getName()
    {
        return 'ComponentSerieCollection';
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
            $this->logger->info('normalized collection before' , [$value]);
            $collection = $this->serializer->normalize($value, null,[AbstractObjectNormalizer::SKIP_UNINITIALIZED_VALUES => false]);
            $this->logger->info('normalized collection' , $collection);
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
        $this->logger->info('database to php value before parse' , [$value]);
        try {
            $array =  json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw ConversionException::conversionFailed($value, $this->getName(), $e);
        }
        $this->logger->info('collection' ,  $array);
        
        $collection = new ComponentSerieCollection();
        forEach($array as $index=>$item) {
            // todo: abide for your sin :)))
            $item = array_combine($this->properties, $item);
            $entity = $this->serializer->deserialize(
                json_encode($item, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION), ComponentSerie::class, 'json', 
                [AbstractObjectNormalizer::DISABLE_TYPE_ENFORCEMENT => true]
            );
            $collection[] = $entity;
            
        }
        $this->logger->info('database to php value' , [$collection]);
        return $collection;
    }
}