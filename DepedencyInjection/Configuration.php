<?php


namespace Ecotone\Symfony\DepedencyInjection;


use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('ecotone');

        $treeBuilder->getRootNode()
            ->children()
                ->booleanNode("loadSrc")
                    ->defaultTrue()
                ->end()
                ->booleanNode("failFast")
                ->end()
                ->arrayNode("namespaces")
                    ->scalarPrototype()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}