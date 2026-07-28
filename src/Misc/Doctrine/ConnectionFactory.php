<?php

namespace App\Misc\Doctrine;

use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Configuration;
use Doctrine\Common\EventManager;
use App\Misc\Doctrine\Type\ServiceTypeInterface;
use Doctrine\Bundle\DoctrineBundle\ConnectionFactory as BaseFactory;

class ConnectionFactory extends BaseFactory
{
    protected $serviceTypes;

    public function registerServiceType(ServiceTypeInterface $serviceType)
    {
        $this->serviceTypes[get_class($serviceType)] = $serviceType;
    }
    
    public function createConnection(
        array $params,
        Configuration $config = null,
        EventManager $eventManager = null,
        array $mappingTypes = []
    )
    {
        $reflect = new \ReflectionProperty(BaseFactory::class, 'initialized');
        $reflect->setAccessible(true);

        if (!$reflect->getValue($this)) {
            $typesReflect = new \ReflectionProperty(BaseFactory::class, 'typesConfig');
            $typesReflect->setAccessible(true);
            
            foreach ($typesReflect->getValue($this) as $typeName => $typeConfig) {
                if (is_a($typeConfig['class'], ServiceTypeInterface::class, true)) {
                    $registry = Type::getTypeRegistry();
                    
                    if ($registry->has($typeName)) {
                        $registry->override($typeName, $this->serviceTypes[$typeConfig['class']]);
                    }
                    else {
                        $registry->register($typeName, $this->serviceTypes[$typeConfig['class']]);
                    }
                }
                elseif (Type::hasType($typeName)) {
                    Type::overrideType($typeName, $typeConfig['class']);
                }
                else {
                    Type::addType($typeName, $typeConfig['class']);
                }
            }
            
            $reflect->setValue($this, true);
        }            

        return parent::createConnection($params, $config, $eventManager, $mappingTypes);
    }
}