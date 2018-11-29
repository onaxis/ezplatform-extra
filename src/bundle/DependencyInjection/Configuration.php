<?php

/**
 * @copyright Copyright (C) Onaxis. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace Onaxis\EzPlatformExtraBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use eZ\Bundle\EzPublishCoreBundle\DependencyInjection\Configuration\SiteAccessAware\Configuration as SiteAccessConfiguration;

/**
 * This is the class that validates and merges configuration from your app/config files.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/configuration.html}
 */
class Configuration extends SiteAccessConfiguration
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('onaxis_ez_platform_extra');

        $rootNode
            ->children()
                ->arrayNode('user_self_edit_form_filters')
                    ->prototype('array')
                        ->children()
                            ->enumNode('type')->values(['include/exclude', 'exclude/include'])->defaultValue('include/exclude')->end()
                            ->arrayNode('include')->defaultValue(['*'])->prototype('scalar')->end()->end()
                            ->arrayNode('exclude')->prototype('scalar')->end()->end()
                            ->booleanNode('enabled')->defaultTrue()->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
