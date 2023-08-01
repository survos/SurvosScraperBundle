<?php

namespace Survos\Scraper;

use Survos\Scraper\Service\ScraperService;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class SurvosScraperBundle extends AbstractBundle
{
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $serviceId = 'survos_scraper.scraper_service';
        $container->services()->alias(ScraperService::class, $serviceId);
        $definition = $builder->autowire($serviceId, ScraperService::class)
            ->setPublic(true);

        $definition->setArgument('$cache', new Reference('cache.app'));
        $definition->setArgument('$httpClient', new Reference('http_client'));

        $definition->setArgument('$dir', $config['dir']);
        $definition->setArgument('$prefix', $config['prefix']);
        $definition->setArgument('$sqliteFilename', $config['sqliteFilename']);
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('dir')->defaultValue('cache')->end()
                ->scalarNode('prefix')->defaultValue('')->end()
                ->scalarNode('sqliteFilename')->defaultValue('scraper.sqlite')->end()
            ->end();
    }

}
