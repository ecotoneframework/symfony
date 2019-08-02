<?php

namespace Ecotone\Symfony;

use Doctrine\Common\Annotations\AnnotationReader;
use Ecotone\Messaging\Config\Annotation\AnnotationModuleRetrievingService;
use Ecotone\Messaging\Config\Annotation\FileSystemAnnotationRegistrationService;
use Ecotone\Messaging\Config\Configuration;
use Ecotone\Messaging\Config\MessagingSystemConfiguration;
use Ecotone\Messaging\Handler\Gateway\GatewayProxyConfiguration;
use ProxyManager\GeneratorStrategy\EvaluatingGeneratorStrategy;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Class IntegrationMessagingCompilerPass
 * @package Ecotone\Symfony
 * @author Dariusz Gafka <dgafka.mail@gmail.com>
 */
class IntegrationMessagingCompilerPass implements CompilerPassInterface
{
    /**
     * @param ContainerBuilder $container
     * @return Configuration
     * @throws \Doctrine\Common\Annotations\AnnotationException
     * @throws \Ecotone\Messaging\Config\ConfigurationException
     * @throws \Ecotone\Messaging\Handler\TypeDefinitionException
     * @throws \Ecotone\Messaging\MessagingException
     */
    public function process(ContainerBuilder $container)
    {
        $namespaces = array_merge(
            $container->hasParameter('messaging.application.context.namespace') ? $container->getParameter('messaging.application.context.namespace') : [],
            [FileSystemAnnotationRegistrationService::FRAMEWORK_NAMESPACE]
        );
        $isProductionEnvironment = $container->getParameter("kernel.environment") === 'prod';

        $proxyConfigurationDefinition = new Definition();
        $proxyConfigurationDefinition->setClass(\ProxyManager\Configuration::class);
//        $config->setGeneratorStrategy(new EvaluatingGeneratorStrategy());
        if ($isProductionEnvironment) {
            $proxyConfigurationDefinition->addMethodCall('setProxiesTargetDir', ["%kernel.cache_dir%/%kernel.environment%/ecotone"]);
        }
        $proxyConfigurationDefinition->setPublic(true);
        $container->setDefinition(GatewayProxyConfiguration::REFERENCE_NAME, $proxyConfigurationDefinition);


        $messagingConfiguration =  MessagingSystemConfiguration::createWithCachedReferenceObjectsForNamespaces(
            realpath($container->getParameter('kernel.root_dir') . "/.."),
            $namespaces,
            new SymfonyReferenceTypeResolver($container),
            $container->getParameter("kernel.environment"),
            $isProductionEnvironment,
            true
        );

        foreach ($messagingConfiguration->getRegisteredGateways() as $referenceName => $interface) {
            $definition = new Definition();
            $definition->setFactory([ProxyGenerator::class, 'createFor']);
            $definition->setClass($interface);
            $definition->addArgument($referenceName);
            $definition->addArgument(new Reference('service_container'));
            $definition->addArgument($interface);
            $definition->addArgument(new Reference(GatewayProxyConfiguration::REFERENCE_NAME));
            $definition->setPublic(true);

            $container->setDefinition($referenceName, $definition);
        }

        foreach ($messagingConfiguration->getRequiredReferences() as $requiredReference) {
            $alias = $container->setAlias($requiredReference . '-proxy', $requiredReference);

            if ($alias) {
                $alias->setPublic(true);
            }
        }

        $container->setParameter(IntegrationMessagingBundle::MESSAGING_SYSTEM_CONFIGURATION_SERVICE_NAME, serialize($messagingConfiguration));
    }
}