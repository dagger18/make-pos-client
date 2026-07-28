<?php

namespace App;

use App\Misc\Attribute\AppModule;
use App\Misc\Attribute\Log;
use Doctrine\DBAL\Types\Type;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\Config\Resource\GlobResource;

class Kernel extends BaseKernel implements CompilerPassInterface
{
    use MicroKernelTrait;
    protected function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(
            new class implements CompilerPassInterface {
                public function process(ContainerBuilder $container): void
                {
                    if (!$container->hasDefinition('app.auto_service_locator')) {
                        return;
                    }
                    $locator = $container->getDefinition('app.auto_service_locator');
                    $services = $locator->getArgument(0);
                    foreach (array_keys($container->getDefinitions()) as $id) {
                        if (!preg_match('/^App\\\\Module\\\\[^\\\\]+\\\\Service\\\\(\w+)$/', $id, $m)) {
                            continue;
                        }
                        $services[$id] = new Reference($id);
                        // Short alias: App\Service\LogService → App\Module\*\Service\LogService
                        // Allows legacy lookups by class basename; first match wins.
                        $shortKey = 'App\\Service\\' . $m[1];
                        if (!isset($services[$shortKey])) {
                            $services[$shortKey] = new Reference($id);
                        }
                    }
                    $locator->setArgument(0, $services);
                }
            },
            PassConfig::TYPE_BEFORE_OPTIMIZATION,
            9
        );

        $container->registerAttributeForAutoconfiguration(
            Log::class,
            static function (
                ChildDefinition $definition,
                Log $attribute,
                \ReflectionMethod $reflector
            ): void {
                //dd($definition);
            }
        );
    }
    public function process(ContainerBuilder $container)
    {
        $definition = $container->getDefinition('doctrine.dbal.connection_factory');

        foreach ($container->findTaggedServiceIds('app.doctrine.dbal.service_type') as $id => $_) {
            $definition->addMethodCall('registerServiceType', [new Reference($id)]);
        }

        $dir   = $container->getParameter('kernel.project_dir') . '/config/serializer_groups';
        $container->addResource(new GlobResource($dir, '/*.yaml', false));
        $files = glob($dir . '/*.yaml') ?: [];
        if ($files) {
            $container->getDefinition(\App\Serializer\GroupCentricYamlLoader::class)
                ->setArgument('$files', $files);

            $chainLoader = $container->getDefinition('serializer.mapping.chain_loader');
            $loaders     = $chainLoader->getArgument(0);
            $loaders[]   = new Reference(\App\Serializer\GroupCentricYamlLoader::class);
            $chainLoader->setArgument(0, $loaders);
        }

    }
}
