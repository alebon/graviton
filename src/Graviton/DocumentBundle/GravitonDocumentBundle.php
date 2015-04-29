<?php
/**
 * integrate the mongodb flavour of the doctrine2-odm with graviton
 */

namespace Graviton\DocumentBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Graviton\BundleBundle\GravitonBundleInterface;
use Doctrine\Bundle\MongoDBBundle\DoctrineMongoDBBundle;
use Stof\DoctrineExtensionsBundle\StofDoctrineExtensionsBundle;
use Doctrine\Bundle\FixturesBundle\DoctrineFixturesBundle;
use Doctrine\ODM\MongoDB\Types\Type;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Graviton\DocumentBundle\DependencyInjection\Compiler\ExtRefMappingCompilerPass;
use Graviton\DocumentBundle\DependencyInjection\Compiler\ExtRefFieldsCompilerPass;

/**
 * GravitonDocumentBundle
 *
 * @author   List of contributors <https://github.com/libgraviton/graviton/graphs/contributors>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     http://swisscom.ch
 */
class GravitonDocumentBundle extends Bundle implements GravitonBundleInterface
{
    /**
     * initialize bundle
     */
    public function __construct()
    {
        Type::registerType('extref', 'Graviton\DocumentBundle\Types\ExtReference');
    }

    /**
     * inject services into custom type
     *
     * @return void
     */
    public function boot()
    {
        /* @var $router Router */
        $router = $this->container->get('router');

        /* @var $type \Graviton\DocumentBundle\Types\ExtReference */
        $type = Type::getType('extref');

        $type->setRouter($router);
        $type->setMapping($this->container->getParameter('graviton.document.type.extref.mapping'));
    }


    /**
     * {@inheritDoc}
     *
     * @return \Symfony\Component\HttpKernel\Bundle\Bundle[]
     */
    public function getBundles()
    {
        return array(
            new DoctrineMongoDBBundle(),
            new StofDoctrineExtensionsBundle(),
            new DoctrineFixturesBundle(),
        );
    }

    /**
     * load compiler pass
     *
     * @param ContainerBuilder $container container builder
     *
     * @return void
     */
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addCompilerPass(new ExtRefMappingCompilerPass);
        $container->addCompilerPass(new ExtRefFieldsCompilerPass);
    }
}
