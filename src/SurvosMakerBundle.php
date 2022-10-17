<?php

/*
 * This file is based on the Symfony MakerBundle package.
 */

namespace Survos\Bundle\MakerBundle;

use Doctrine\Migrations\Configuration\Migration\JsonFile;
use Survos\Bundle\MakerBundle\Command\ClassUpdateCommand;
use Survos\Bundle\MakerBundle\DependencyInjection\Compiler\SurvosMakerCompilerPass;
use Survos\Bundle\MakerBundle\Maker\MakeBundle;
use Survos\Bundle\MakerBundle\Maker\MakeCrud;
use Survos\Bundle\MakerBundle\Maker\MakeInvokableCommand;
use Survos\Bundle\MakerBundle\Maker\MakeMenu;
//use Survos\Bundle\MakerBundle\Maker\MakeMethod;
use Survos\Bundle\MakerBundle\Maker\MakeModel;
use Survos\Bundle\MakerBundle\Maker\MakeParamConverter;
use Survos\Bundle\MakerBundle\Maker\MakeService;
use Survos\Bundle\MakerBundle\Maker\MakeWorkflow;
use Survos\Bundle\MakerBundle\Maker\MakeWorkflowListener;
use Survos\Bundle\MakerBundle\Renderer\ParamConverterRenderer;
use Survos\Bundle\MakerBundle\Service\MakerService;
use Symfony\Bundle\MakerBundle\DependencyInjection\CompilerPass\MakeCommandRegistrationPass;
use Symfony\Bundle\MakerBundle\DependencyInjection\CompilerPass\RemoveMissingParametersPass;
use Symfony\Bundle\MakerBundle\DependencyInjection\CompilerPass\SetDoctrineAnnotatedPrefixesPass;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Component\HttpKernel\Bundle\Bundle;

use Symplify\ComposerJsonManipulator\ComposerJsonFactory;
use Symplify\ComposerJsonManipulator\FileSystem\JsonFileManager;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

class SurvosMakerBundle extends AbstractBundle implements CompilerPassInterface
{
    // The compiler pass
    public function process(ContainerBuilder $container)
    {
        //        $map = [];
        //        // get the map from serviceIds to classes, so we can inject things like router.default and serializer
        //        foreach ($container->getDefinitions() as $id => $definition) {
        //            $map[$id] = $definition->getClass();
        //        }
        //
        //        //        $builder = $this->getContainerBuilder($this->getApplication()->getKernel());
        //        $serviceIds = $container->getServiceIds();
        //        dd($serviceIds);
        //
        //
        //        $definition = $container->getDefinition(MakerService::class);
        //        $definition->addMethodCall(
        //            'setIdMap',
        //            [$map]
        //        );
        //

        if (false === $container->hasDefinition('workflow.registry')) {
            return;
        }
        //        $reference = new Reference('workflow.registry');

        $container->get(MakeWorkflowListener::class)
            ->setArgument('registry', new Reference('workflow.registry'))
        ;
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        foreach ([MakeMenu::class, MakeService::class, MakeInvokableCommand::class, MakeModel::class] as $makerClass) {
            $builder->autowire($makerClass)
                ->addTag(MakeCommandRegistrationPass::MAKER_TAG) // 'maker.command'
                ->addArgument(new Reference('maker.generator'))
                ->addArgument($config['template_path'])
            ;
        }

        $builder->register( JsonFileManager::class, $serviceId = 'json_file_manager')
            ->setAutowired(true)
            ->setAutoconfigured(true);

        $builder->autowire(MakeBundle::class)
            ->addTag(MakeCommandRegistrationPass::MAKER_TAG) // 'maker.command'
            ->addArgument($config['template_path'])
            ->addArgument($config['relative_bundle_path']) // /packages
            ->addArgument($config['bundle_name'])
//            ->setArgument('$jsonFileManager', $serviceId)
//            ->setArgument('$jsonFileManager', new Reference(JsonFileManager::class))
//            ->setArgument('$composerJsonFactory', new Reference(ComposerJsonFactory::class))
        ;

        // we can likely combine these, or even move it to crud
        $builder->register('maker.param_converter_renderer', ParamConverterRenderer::class)
            ->addArgument(new Reference('maker.generator'))
            ->addArgument($config['template_path']);

        $builder->autowire(MakeParamConverter::class)
            ->addTag('maker.command')
            ->addArgument(new Reference('maker.doctrine_helper'))
//                ->addArgument(new Reference('maker.generator'))
            ->addArgument(new Reference('maker.param_converter_renderer'))
            ->addArgument($config['template_path'])
            ->addArgument(new Reference('parameter_bag'))

        ;

        $builder->autowire(MakeCrud::class)
            ->addTag('maker.command')
            ->addArgument(new Reference('maker.doctrine_helper'))
            ->addArgument(new Reference('maker.renderer.form_type_renderer'))
        ;

        $definition = $builder->autowire(MakeWorkflowListener::class)
            ->addTag('maker.command')
            ->addArgument(new Reference('maker.doctrine_helper'))
            ->addArgument(new Reference('maker.generator'))
        ;

        $builder->autowire(ClassUpdateCommand::class)
            ->addTag('console.command')
            ->addTag('container.service_subscriber')
//            ->setAutoconfigured(true)
//            ->addMethodCall('setInvokeContainer', [new Reference('service_container')])
        ;

        //        dd(service('maker.doctrine_helper')->nullOnInvalid(), service('workflow.registry')->nullOnInvalid(), service('x')->nullOnInvalid());
        //            $definition
        //                ->addArgument(new Reference('workflow.registry'))
        //            ;
        //        try {
        //        } catch (\Exception $exception) {
        //            // there must be a better way to only wire this if it exists.
        //        }


        $builder->autowire(MakerService::class)
            ->setArgument('$propertyAccessor', new Reference('property_accessor'))
            ->setArgument('$twig', new Reference('twig'))
        ;

        $builder->autowire(MakeWorkflow::class)
            ->addTag('maker.command')
            ->addArgument(new Reference('maker.doctrine_helper'))
            ->addArgument($config['template_path'])
        ;
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
            ->scalarNode('template_path')->defaultValue(__DIR__ . '/../templates/skeleton/')->end()
            ->scalarNode('vendor')->defaultValue('Survos')->end()
            ->scalarNode('bundle_name')->defaultValue('FooBundle')->end()
            ->scalarNode('relative_bundle_path')->defaultValue('packages')->end()
            ->end();
    }

    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addCompilerPass($this, PassConfig::TYPE_BEFORE_OPTIMIZATION, -1000);

        return;

        // add a priority so we run before the core command pass
        //        $container->addCompilerPass(new DoctrineAttributesCheckPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 11);
        $container->addCompilerPass(new MakeCommandRegistrationPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 10);
        $container->addCompilerPass(new RemoveMissingParametersPass());
        //        $container->addCompilerPass(new SetDoctrineManagerRegistryClassPass());
        $container->addCompilerPass(new SetDoctrineAnnotatedPrefixesPass());

        // Register this class as a pass, to eliminate the need for the extra DI class
        // https://stackoverflow.com/questions/73814467/how-do-i-add-a-twig-global-from-a-bundle-config

        //        dump(__FILE__, __LINE__);
        //        $container->addCompilerPass(new SurvosMakerCompilerPass());
    }
}
