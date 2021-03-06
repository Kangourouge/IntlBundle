<?php

namespace KRG\IntlBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/configuration.html}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('krg_intl');

        $rootNode->children()
            ->arrayNode('locales')
                ->prototype('scalar')->end()
                ->defaultValue([])
            ->end()
            ->arrayNode('domains')
                ->prototype('scalar')->end()
                ->defaultValue([])
            ->end()
            ->booleanNode('legacy')->defaultValue(false)->end()
            ->scalarNode('cache_dir')->defaultValue('%kernel.cache_dir%/krg/translations')->end();

        return $treeBuilder;
    }
}
