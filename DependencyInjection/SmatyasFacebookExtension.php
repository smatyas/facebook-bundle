<?php

namespace Smatyas\FacebookBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * @link http://symfony.com/doc/current/cookbook/bundles/extension.html
 */
class SmatyasFacebookExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');

        $this->loadProfilerCollector($container, $loader);
    }

    private function loadProfilerCollector(ContainerBuilder $container, Loader\YamlFileLoader $loader)
    {
        if ($container->getParameter('kernel.debug')) {
            $loader->load('collector.yml');

            $serviceDefinition = $container->getDefinition('facebook');
            $serviceDefinition->addArgument(new Reference('debug.stopwatch'));

            $container->setDefinition('facebook', $serviceDefinition);
        }
    }
}
