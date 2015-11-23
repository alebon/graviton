<?php
/**
 * LoaderInterface
 */

namespace Graviton\ProxyBundle\Definition\Loader;

use Graviton\ProxyBundle\Definition\ApiDefinition;
use Graviton\ProxyBundle\Definition\Loader\CacheStrategy\CacheStrategyInterface;
use Graviton\ProxyBundle\Definition\Loader\DispersalStrategy\DispersalStrategyInterface;

/**
 * LoaderInterface
 *
 * @author  List of contributors <https://github.com/libgraviton/graviton/graphs/contributors>
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link    http://swisscom.ch
 */
interface LoaderInterface
{
    /**
     * set options
     *
     * @param array $options array of options
     *
     * @return void
     */
    public function setOptions($options);

    /**
     * set a load strategy
     *
     * @param DispersalStrategyInterface $strategy strategy to add
     *
     * @return void
     */
    public function setDispersalStrategy($strategy);

    /**
     * set a cache strategy
     *
     * @param CacheStrategyInterface $strategy strategy to add
     *
     * @return void
     */
    public function setCacheStrategy($strategy);

    /**
     * check if the input is supported
     *
     * @param string $input input
     *
     * @return boolean
     */
    public function supports($input);

    /**
     * @param string|null $input input
     *
     * @return ApiDefinition
     */
    public function load($input);
}
