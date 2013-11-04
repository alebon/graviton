<?php
/**
 * manage and load bundle config.
 */

namespace Graviton\CoreBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;

/**
 * This is the class that loads and manages your bundle configuration
 *
 * To learn more see {@link http://scm.to/004w}
 *
 * @category Graviton
 * @package  Graviton
 * @author   Lucas Bickel <lucas.bickel@swisscom.com>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     http://swisscom.com
 */
class GravitonCoreExtension extends Extension implements PrependExtensionInterface
{
    /**
     * {@inheritDoc}
     *
     * @param Array            $configs   configs
     * @param ContainerBuilder $container container builder
     *
     * @return void
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\XmlFileLoader(
            $container,
            new FileLocator(__DIR__.'/../Resources/config')
        );
        $loader->load('services.xml');
    }

    /**
     * {@inheritDoc}
     *
     * Load additional config into the container.
     *
     * @param ContainerBuilder $container instance
     *
     * @return void
     */
    public function prepend(ContainerBuilder $container)
    {
        $loader = new Loader\XmlFileLoader(
            $container,
            new FileLocator(__DIR__.'/../Resources/config')
        );
        $loader->load('config.xml');
    }

}
