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

use Psr\Log\LoggerInterface;
use App\Module\Core\Service\RequestService;
use Doctrine\ORM\NoResultException;
use Doctrine\Persistence\ObjectManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\DBAL\Types\ConversionException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Doctrine\ORM\Persisters\Exception\UnrecognizedField;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Yields the entity matching the criteria provided in the route.
 * https://github.com/symfony/symfony/blob/7.0/src/Symfony/Bridge/Doctrine/ArgumentResolver/EntityValueResolver.php
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class CrudEntityValueResolver implements ValueResolverInterface
{
    public function __construct(
        private ManagerRegistry $registry,
        private readonly LoggerInterface $logger,
        private RequestService $requestService,
        private readonly TranslatorInterface $translator,
        private ?ExpressionLanguage $expressionLanguage = null,
        private MapEntity $defaults = new MapEntity(),
    ) {
    }

    public function resolve(Request $request, ArgumentMetadata $argument): array
    {
        if (\is_object($request->attributes->get($argument->getName()))) {
            return [];
        }
        
        $options = $argument->getAttributes(MapEntity::class, ArgumentMetadata::IS_INSTANCEOF);
        $options = ($options[0] ?? $this->defaults)->withDefaults($this->defaults, $argument->getType());
         
        if (!$options->class) {
            preg_match('/\\\\([a-zA-Z]+)Controller/m', $request->attributes->get('_controller'), $match);
            if($match[1] === 'ApiAuth') return [];
            if($match[1] === 'Auth') return [];
            if($match[1] === 'Public') return [];
            if($match[1] === 'Redirect') return [];
            if($match[1] === 'MyProfile') return [];
            // Derive entity from controller FQCN: App\Module\Foo\Controller\BarController → App\Module\Foo\Entity\Bar
            if (preg_match('/^App\\\\Module\\\\([^\\\\]+)\\\\Controller\\\\/', $request->attributes->get('_controller'), $modMatch)) {
                $options->class = 'App\\Module\\' . $modMatch[1] . '\\Entity\\' . $match[1];
            } else {
                $options->class = 'App\\Entity\\' . $match[1];
            }
        }
        if (!$options->class || $options->disabled) {
            return [];
        }
        //dd($options->class);
        if (!$manager = $this->getManager($options->objectManager, $options->class)) {
            return [];
        }
        $this->logger->info($options->expr ? 'Resolving entity using expression: ' . $options->expr : 'Resolving entity of class: ' . $options->class);
        $message = '';
        if (null !== $options->expr) {
            if (null === $object = $this->findViaExpression($manager, $request, $options)) {
                $message = sprintf(' The expression "%s" returned null.', $options->expr);
            }
        // find by identifier?
        } elseif (false === $object = $this->find($manager, $request, $options, $argument->getName())) {
            // find by criteria
            if (!$criteria = $this->getCriteria($request, $options, $manager)) {
                return [];
            }
             
            try {
                $this->logger->info('Finding entity ' . $options->class . ' by criteria: ' . json_encode($criteria));
                $object = $manager->getRepository($options->class)->findOneBy($criteria);
            } catch (NoResultException|ConversionException) {
                $object = null;
            }
        }

        if (null === $object) {
            throw new NotFoundHttpException(sprintf($this->translator->trans('%s not found'), $match[1] ?? 'Entity').$message);
        }
        
        // check ownership please
        $ownershipFields = $this->requestService->getOwnershipFields($request);
        // no need to check, return object
        if(empty($ownershipFields)) return [$object];
        // check every fields
        forEach(explode(',', $ownershipFields) as $ownershipField) {
          if(method_exists($object, 'get' . ucfirst($ownershipField))
            && $object->{'get' . ucfirst($ownershipField)}() === $this->requestService->getUser()
            ) {
              return [$object];
          } 
        }
        throw new NotFoundHttpException(sprintf($this->translator->trans('%s not found'), $match[1] ?? 'Entity').$message);
    }

    private function getManager(?string $name, string $class): ?ObjectManager
    {
        if (null === $name) {
            return $this->registry->getManagerForClass($class);
        }

        try {
            $manager = $this->registry->getManager($name);
        } catch (\InvalidArgumentException) {
            return null;
        }

        return $manager->getMetadataFactory()->isTransient($class) ? null : $manager;
    }

    private function find(ObjectManager $manager, Request $request, MapEntity $options, string $name): false|object|null
    {
        if ($options->mapping || $options->exclude) {
            return false;
        }

        $id = $this->getIdentifier($request, $options, $name);
        if (false === $id || null === $id) {
            return $id;
        }

        if ($options->evictCache && $manager instanceof EntityManagerInterface) {
            $cacheProvider = $manager->getCache();
            if ($cacheProvider && $cacheProvider->containsEntity($options->class, $id)) {
                $cacheProvider->evictEntity($options->class, $id);
            }
        }
        try {
            return $manager->getRepository($options->class)->find($id);
        } catch (NoResultException|ConversionException|UnrecognizedField) {
            return false;
        }
    }

    private function getIdentifier(Request $request, MapEntity $options, string $name): mixed
    {
        if (\is_array($options->id)) {
            $id = [];
            foreach ($options->id as $field) {
                // Convert "%s_uuid" to "foobar_uuid"
                if (str_contains($field, '%s')) {
                    $field = sprintf($field, $name);
                }

                $id[$field] = $request->attributes->get($field);
            }

            return $id;
        }

        if (null !== $options->id) {
            $name = $options->id;
        }

        if ($request->attributes->has($name)) {
            return $request->attributes->get($name) ?? ($options->stripNull ? false : null);
        }

        if (!$options->id && $request->attributes->has('id')) {
            return $request->attributes->get('id') ?? ($options->stripNull ? false : null);
        }

        return false;
    }

    private function getCriteria(Request $request, MapEntity $options, ObjectManager $manager): array
    {
        if (null === $mapping = $options->mapping) {
            $mapping = $request->attributes->keys();
        }

        if ($mapping && \is_array($mapping) && array_is_list($mapping)) {
            $mapping = array_combine($mapping, $mapping);
        }

        foreach ($options->exclude as $exclude) {
            unset($mapping[$exclude]);
        }

        if (!$mapping) {
            return [];
        }

        // if a specific id has been defined in the options and there is no corresponding attribute
        // return false in order to avoid a fallback to the id which might be of another object
        if (\is_string($options->id) && null === $request->attributes->get($options->id)) {
            return [];
        }

        $criteria = [];
        $metadata = $manager->getClassMetadata($options->class);

        foreach ($mapping as $attribute => $field) {
            if (!$metadata->hasField($field) && (!$metadata->hasAssociation($field) || !$metadata->isSingleValuedAssociation($field))) {
                continue;
            }

            $criteria[$field] = $request->attributes->get($attribute);
        }

        if ($options->stripNull) {
            $criteria = array_filter($criteria, static fn ($value) => null !== $value);
        }

        return $criteria;
    }

    private function findViaExpression(ObjectManager $manager, Request $request, MapEntity $options): ?object
    {
        if (!$this->expressionLanguage) {
            throw new \LogicException(sprintf('You cannot use the "%s" if the ExpressionLanguage component is not available. Try running "composer require symfony/expression-language".', __CLASS__));
        }

        $repository = $manager->getRepository($options->class);
        $variables = array_merge($request->attributes->all(), ['repository' => $repository]);

        try {
            return $this->expressionLanguage->evaluate($options->expr, $variables);
        } catch (NoResultException|ConversionException) {
            return null;
        }
    }
}
