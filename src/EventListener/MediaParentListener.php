<?php

namespace App\EventListener;

use App\Module\Crm\Entity\Client;
use App\Module\Core\Entity\Media;
use App\Module\Core\Entity\User;
use App\Misc\Attribute\MediaProperty;
use App\Module\Core\Enum\EntityType;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\PersistentCollection;

#[AsDoctrineListener(event: Events::onFlush)]
#[AsDoctrineListener(event: Events::postFlush)]
class MediaParentListener
{
    /** @var object[] */
    private array $pendingInserts = [];

    /** @var array<class-string, array<string, bool>> */
    private array $reflectionCache = [];

    private static array $entityTypeMap = [
        User::class => EntityType::User,
        Client::class => EntityType::Client,
    ];

    public function onFlush(OnFlushEventArgs $args): void
    {
        $em = $args->getObjectManager();
        assert($em instanceof EntityManagerInterface);
        $uow = $em->getUnitOfWork();
        $mediaMeta = $em->getClassMetadata(Media::class);

        $insertedEntities = $uow->getScheduledEntityInsertions();

        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            $props = $this->getMediaProperties($entity);
            if (empty($props)) continue;
            $parentType = $this->resolveEntityType($entity);
            if ($parentType === null) continue;

            $changeset = $uow->getEntityChangeSet($entity);
            foreach ($props as $propName => $isCollection) {
                if ($isCollection || !isset($changeset[$propName])) continue;
                [$old, $new] = $changeset[$propName];
                if ($old instanceof Media) {
                    $old->setParentType(null)->setParentId(null)->setParentProperty(null);
                    $uow->recomputeSingleEntityChangeSet($mediaMeta, $old);
                }
                if ($new instanceof Media) {
                    $new->setParentType($parentType)->setParentId($entity->getId())->setParentProperty($propName);
                    $uow->recomputeSingleEntityChangeSet($mediaMeta, $new);
                }
            }
        }

        foreach ($uow->getScheduledCollectionUpdates() as $collection) {
            /** @var PersistentCollection $collection */
            $owner = $collection->getOwner();
            if ($owner === null || in_array($owner, $insertedEntities, true)) continue;

            $propName = $collection->getMapping()['fieldName'];
            $props = $this->getMediaProperties($owner);
            if (!isset($props[$propName])) continue;

            $parentType = $this->resolveEntityType($owner);
            if ($parentType === null) continue;

            foreach ($collection->getInsertDiff() as $media) {
                if (!$media instanceof Media) continue;
                $media->setParentType($parentType)->setParentId($owner->getId())->setParentProperty($propName);
                $uow->recomputeSingleEntityChangeSet($mediaMeta, $media);
            }
            foreach ($collection->getDeleteDiff() as $media) {
                if (!$media instanceof Media) continue;
                $media->setParentType(null)->setParentId(null)->setParentProperty(null);
                $uow->recomputeSingleEntityChangeSet($mediaMeta, $media);
            }
        }

        foreach ($insertedEntities as $entity) {
            if (!empty($this->getMediaProperties($entity)) && $this->resolveEntityType($entity) !== null) {
                $this->pendingInserts[] = $entity;
            }
        }
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if (empty($this->pendingInserts)) return;

        $entities = $this->pendingInserts;
        $this->pendingInserts = [];
        $em = $args->getObjectManager();
        assert($em instanceof EntityManagerInterface);
        $conn = $em->getConnection();

        foreach ($entities as $entity) {
            $parentType = $this->resolveEntityType($entity);
            $parentId = $entity->getId();
            if ($parentId === null) continue;

            foreach ($this->getMediaProperties($entity) as $propName => $isCollection) {
                $refProp = $this->getReflectionProperty(get_class($entity), $propName);
                $value = $refProp->getValue($entity);

                $medias = $isCollection
                    ? ($value !== null ? $value->toArray() : [])
                    : ($value instanceof Media ? [$value] : []);

                foreach ($medias as $media) {
                    if (!$media instanceof Media || $media->getId() === null) continue;
                    $conn->update('media', [
                        'parent_type' => $parentType->value,
                        'parent_id' => $parentId,
                        'parent_property' => $propName,
                    ], ['id' => $media->getId()]);
                    $media->setParentType($parentType)->setParentId($parentId)->setParentProperty($propName);
                }
            }
        }
    }

    /** @return array<string, bool> propName => isCollection */
    private function getMediaProperties(object $entity): array
    {
        $class = get_class($entity);
        if (array_key_exists($class, $this->reflectionCache)) {
            return $this->reflectionCache[$class];
        }

        $result = [];
        foreach ((new \ReflectionClass($entity))->getProperties() as $prop) {
            if (empty($prop->getAttributes(MediaProperty::class))) continue;
            $type = $prop->getType();
            $typeName = $type instanceof \ReflectionNamedType ? $type->getName() : '';
            $result[$prop->getName()] = str_contains($typeName, 'Collection');
        }

        return $this->reflectionCache[$class] = $result;
    }

    private function getReflectionProperty(string $class, string $propName): \ReflectionProperty
    {
        return new \ReflectionProperty($class, $propName);
    }

    private function resolveEntityType(object $entity): ?EntityType
    {
        return self::$entityTypeMap[get_class($entity)] ?? null;
    }
}
