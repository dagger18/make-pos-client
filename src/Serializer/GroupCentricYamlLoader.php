<?php
namespace App\Serializer;

use Symfony\Component\Serializer\Mapping\AttributeMetadata;
use Symfony\Component\Serializer\Mapping\ClassMetadataInterface;
use Symfony\Component\Serializer\Mapping\Loader\LoaderInterface;
use Symfony\Component\Yaml\Yaml;

class GroupCentricYamlLoader implements LoaderInterface
{
    public function __construct(private array $files = []) {}

    public function loadClassMetadata(ClassMetadataInterface $classMetadata): bool
    {
        foreach ($this->files as $file) {
            $data = Yaml::parseFile($file);
            if (!isset($data[$classMetadata->getName()])) {
                continue;
            }

            $config       = $data[$classMetadata->getName()];
            $allProps     = $config['_all']    ?? [];
            $ignoredProps = $config['_ignore'] ?? [];
            unset($config['_all'], $config['_ignore']);

            $allGroups = array_keys($config);

            // resolve _extends:groupName inheritance in each group
            $rawGroups = [];
            foreach ($config as $group => $properties) {
                $extends  = [];
                $excludes = [];
                $own      = [];
                foreach ($properties as $prop) {
                    if (str_starts_with((string) $prop, '_extends:')) {
                        $extends[] = substr($prop, strlen('_extends:'));
                    } elseif (str_starts_with((string) $prop, '_exclude:')) {
                        $excludes[] = substr($prop, strlen('_exclude:'));
                    } else {
                        $own[] = $prop;
                    }
                }
                $rawGroups[$group] = ['extends' => $extends, 'props' => $own, 'excludes' => $excludes];
            }

            // Auto-generate subList as alias of list when not explicitly defined
            if (isset($rawGroups['list']) && !isset($rawGroups['subList'])) {
                $rawGroups['subList'] = ['extends' => ['list'], 'props' => [], 'excludes' => []];
                $allGroups[] = 'subList';
            }

            $resolved = [];
            $resolve  = function (string $group) use (&$resolve, $rawGroups, &$resolved): array {
                if (isset($resolved[$group])) {
                    return $resolved[$group];
                }
                $props = $rawGroups[$group]['props'];
                foreach ($rawGroups[$group]['extends'] as $parent) {
                    if (isset($rawGroups[$parent])) {
                        $props = array_merge($resolve($parent), $props);
                    }
                }
                $props = array_diff($props, $rawGroups[$group]['excludes']);
                return $resolved[$group] = array_values(array_unique($props));
            };
            foreach (array_keys($rawGroups) as $group) {
                $resolve($group);
            }

            // _all props appear in every group defined in this file
            $propertyGroups = array_fill_keys($allProps, $allGroups);

            foreach ($resolved as $group => $properties) {
                foreach ($properties as $property) {
                    $propertyGroups[$property][] = $group;
                }
            }

            $existing = $classMetadata->getAttributesMetadata();

            foreach ($ignoredProps as $property) {
                $attr = $existing[$property] ?? new AttributeMetadata($property);
                $attr->setIgnore(true);
                $classMetadata->addAttributeMetadata($attr);
            }

            foreach ($propertyGroups as $property => $groups) {
                $attr = $existing[$property] ?? new AttributeMetadata($property);
                foreach (array_unique($groups) as $group) {
                    $attr->addGroup($group);
                }
                $classMetadata->addAttributeMetadata($attr);
            }

            return true;
        }

        return false;
    }
}
