<?php

namespace Survos\Scraper;

use Survos\Scraper\Service\CacheScraperService;
use Survos\Scraper\Service\ScraperService;
use Survos\Scraper\Twig\TwigExtension;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Twig\Environment;

class SurvosScraperBundle extends AbstractBundle
{
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {

        $cacheScraperServiceId = 'survos_scraper.cache_scraper_service';
        $container->services()->alias(CacheScraperService::class, $cacheScraperServiceId);
        $definition = $builder->autowire($cacheScraperServiceId, CacheScraperService::class)
            ->setPublic(true);
        $definition->setArgument('$httpClient', new Reference('http_client'));
        $definition->setArgument('$logger', new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE));
        $definition->setArgument('$dir', $config['dir']); // @todo: allow passing in a service


        // old way, where we handled the caching.  Benefit is for portable caches, though that will eventually change.
        $serviceId = 'survos_scraper.scraper_service';
        $container->services()->alias(ScraperService::class, $serviceId);
        $definition = $builder->autowire($serviceId, ScraperService::class)
            ->setPublic(true);
        $definition->setArgument('$httpClient', new Reference('http_client'));

        $definition->setArgument('$cache', new Reference('cache.app', ContainerInterface::NULL_ON_INVALID_REFERENCE));
        $definition->setArgument('$logger', new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE));


        $definition->setArgument('$dir', $config['dir']);
        $definition->setArgument('$prefix', $config['prefix']);
        $definition->setArgument('$sqliteFilename', $config['sqliteFilename']);

        // if twig is installed, add the extension
        if (class_exists(Environment::class)) {
            $builder
                ->setDefinition('survos.crawler_bundle', new Definition(TwigExtension::class))
                ->addTag('twig.extension')
                ->setArgument('$scraper', new Reference($serviceId))
                ->setPublic(false);
        }


    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('dir')->defaultValue('/tmp/cache')->end()
                ->scalarNode('prefix')->defaultValue('')->end()
                ->scalarNode('sqliteFilename')->defaultValue('scraper.sqlite')->end()
            ->end();
    }

}
