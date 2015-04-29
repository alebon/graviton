<?php
/**
 * build a collection_name to routerId mapping for ExtReference Types
 *
 * This is all done the cheap way by just inferring collection names from
 * the available serviecs that are tagged as rest service. This also means
 * we need to stick to the naming conventions already there even more.
 */

namespace Graviton\DocumentBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @author   List of contributors <https://github.com/libgraviton/graviton/graphs/contributors>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     http://swisscom.ch
 */
class ExtRefMappingCompilerPass extends AbstractExtRefCompilerPass
{
    /**
     * create mapping from services
     *
     * @param ContainerBuilder $container container builder
     * @param array            $services  services to inspect
     *
     * @return void
     */
    public function processServices(ContainerBuilder $container, $services)
    {
        $map = [];
        foreach ($services as $id) {
            list($ns, $bundle,, $doc) = explode('.', $id);
            $map[ucfirst($doc)] = implode('.', [$ns, $bundle, 'rest', $doc, 'get']);
        }
        $container->setParameter('graviton.document.type.extref.mapping', $map);
    }
}
