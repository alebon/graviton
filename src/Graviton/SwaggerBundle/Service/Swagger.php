<?php

namespace Graviton\SwaggerBundle\Service;

use Graviton\SchemaBundle\Model\SchemaModel;
use Graviton\SchemaBundle\SchemaUtils;
use Symfony\Component\Routing\Route;

/**
 * A service that generates a swagger conform service spec dynamically.
 *
 * @category GravitonSwaggerBundle
 * @package  Graviton
 * @author   Dario Nuevo <dario.nuevo@swisscom.com>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     http://swisscom.com
 */
class Swagger
{

    /**
     * @var \Symfony\Component\DependencyInjection\ContainerInterface service_container
     */
    private $container;

    /**
     * @var \Graviton\RestBundle\Service\RestUtils
     */
    private $restUtils;

    /**
     * sets the container
     *
     * @param \Symfony\Component\DependencyInjection\ContainerInterface $container service_container
     *
     * @return void
     */
    public function setContainer($container = null)
    {
        $this->container = $container;
    }

    /**
     * sets restUtils
     *
     * @param \Graviton\RestBundle\Service\RestUtils $restUtils rest utils
     *
     * @return void
     */
    public function setRestUtils($restUtils = null)
    {
        $this->restUtils = $restUtils;
    }

    /**
     * Returns the swagger spec as array
     *
     * @return array Swagger spec
     */
    public function getSwaggerSpec()
    {
        $ret = $this->getBasicStructure();
        $routingMap = $this->restUtils->getServiceRoutingMap();
        $paths = array();

        foreach ($routingMap as $contName => $routes) {

            list($app, $bundle, $rest, $document) = explode('.', $contName);

            foreach ($routes as $routeName => $route) {

                $routeMethod = strtolower($route->getMethods()[0]);

                // skip PATCH (as for now) & /schema/ stuff
                if (
                    strpos($route->getPath(), '/schema/') !== false ||
                    $routeMethod == 'patch'
                ) {
                    continue;
                }

                $thisModel = $this->restUtils->getModelFromRoute($route);
                $entityClassName = str_replace('\\', '', get_class($thisModel));

                $schema = SchemaUtils::getModelSchema($entityClassName, $thisModel, array(), array());

                $ret['definitions'][$entityClassName] = json_decode(
                    $this->restUtils->serializeContent($schema),
                    true
                );

                $isCollectionRequest = true;
                if (in_array('id', array_keys($route->getRequirements())) === true) {
                    $isCollectionRequest = false;
                }

                $thisPattern = $route->getPattern();
                $entityName = ucfirst($document);

                $thisPath = array(
                    'tags' => $this->getPathTags($route),
                    'operationId' => $routeName,
                    'consumes' => array('application/json'),
                    'produces' => array('application/json')
                );

                $thisPath['summary'] = $this->getSummary($routeMethod, $isCollectionRequest, $entityName);

                // collection return or not?
                if (!$isCollectionRequest) {
                    // add object response
                    $thisPath['responses'] = array(
                        200 => array(
                            'description' => $entityName . ' response',
                            'schema' => array('$ref' => '#/definitions/' . $entityClassName)
                        ),
                        404 => array(
                            'description' => 'Resource not found'
                        )
                    );

                    // add id param
                    $thisPath['parameters'][] = array(
                        'name' => 'id',
                        'in' => 'path',
                        'description' => 'ID of ' . $entityName . ' item to fetch/update',
                        'required' => true,
                        'type' => $schema->getProperty('id')
                                         ->getType()
                    );
                } else {
                    // add array response
                    $thisPath['responses'][200] = array(
                        'description' => $entityName . ' response',
                        'schema' => array(
                            'type' => 'array',
                            'items' => array('$ref' => '#/definitions/' . $entityClassName)
                        )
                    );

                    $thisPath['parameters'][] = array(
                        'name' => 'q',
                        'in' => 'query',
                        'description' => 'Optional RQL filter',
                        'required' => false,
                        'type' => 'string'
                    );
                    // paging params
                    $thisPath['parameters'][] = array(
                        'name' => 'page',
                        'in' => 'query',
                        'description' => '(Paging) Page to fetch',
                        'required' => false,
                        'default' => 1,
                        'type' => 'integer'
                    );
                    $thisPath['parameters'][] = array(
                        'name' => 'perPage',
                        'in' => 'query',
                        'description' => '(Paging) Items per page',
                        'required' => false,
                        'default' => 10,
                        'type' => 'integer'
                    );
                }

                // post body stuff
                if ($routeMethod == 'put' || $routeMethod == 'post') {

                    // special handling for POST/PUT.. we need to have 2 schemas, one for response, one for request..
                    // we don't want to have ID in the request body within those requests do we..
                    // an exception is when id is required..
                    $incomingEntitySchema = $entityClassName;
                    if (is_null($schema->getRequired()) || !in_array('id', $schema->getRequired())) {
                        $incomingEntitySchema = $incomingEntitySchema . 'Incoming';
                        $incomingSchema = clone $schema;
                        $incomingSchema->removeProperty('id');
                        $ret['definitions'][$incomingEntitySchema] = json_decode(
                            $this->restUtils->serializeContent($incomingSchema),
                            true
                        );
                    }

                    $thisPath['parameters'][] = array(
                        'name' => $bundle,
                        'in' => 'body',
                        'description' => 'Post',
                        'required' => true,
                        'schema' => array('$ref' => '#/definitions/' . $incomingEntitySchema)
                    );

                    // add error responses..
                    $thisPath['responses'][400] = array(
                        'description' => 'Bad request',
                        'schema' => array(
                            'type' => 'object'
                        )
                    );
                }

                if ($routeMethod == 'options') {
                    $thisPath['responses'][200] = array(
                        'description' => 'Schema response',
                        // http://json-schema.org/draft-04/schema
                        'schema' => array('$ref' => '#/definitions/SchemaModel')
                    );
                }

                $paths[$thisPattern][$routeMethod] = $thisPath;
            }
        }

        $ret['definitions']['SchemaModel'] = $this->getSchemaSchema();

        $ret['paths'] = $paths;

        return $ret;
    }

    /**
     * Basic structure of the spec
     *
     * @return array Basic structure
     */
    private function getBasicStructure()
    {
        $ret = array();
        $ret['swagger'] = '2.0';
        $ret['info'] = array(
            // @todo this should be a real version - but should it be the version of graviton or which one?
            'version' => '0.1',
            'title' => 'Graviton REST Services',
            'description' => 'Testable API Documentation of this Graviton instance.'
        );
        $ret['host'] = $_SERVER['HTTP_HOST'];
        $ret['basePath'] = '/';
        $ret['schemes'] = array('http');

        return $ret;
    }

    /**
     * Returns the definition of a 'schema' response
     *
     * @return stdClass Schema
     */
    protected function getSchemaSchema()
    {
        $schemaModel = new SchemaModel();
        return $schemaModel->getSchema();
    }

    /**
     * Returns the tags (which influences the grouping visually) for a given route
     *
     * @param Route $route route
     *
     * @return array Array of tags..
     */
    protected function getPathTags(Route $route)
    {
        $ret = array();
        $routeParts = explode('/', $route->getPath());
        if (isset($routeParts[1])) {
            $ret[] = ucfirst($routeParts[1]);
        }
        return $ret;
    }

    /**
     * Returns a meaningful summary depending on certain conditions
     *
     * @param string $method              Method
     * @param bool   $isCollectionRequest If collection request
     * @param string $entityName          Name of entity
     *
     * @return string summary
     */
    protected function getSummary($method, $isCollectionRequest, $entityName) {
        $ret = '';
        // meaningful descriptions..
        switch ($method) {
            case 'get':
                if ($isCollectionRequest) {
                    $ret = 'Get collection of ' . $entityName . ' resources';
                } else {
                    $ret = 'Get single ' . $entityName . ' resources';
                }
                break;
            case 'post':
                $ret = 'Create new ' . $entityName . ' resource';
                break;
            case 'options':
                $ret = 'Get schema information for ' . $entityName . ' resource';
                break;
            case 'put':
                $ret = 'Update existing ' . $entityName . ' resource';
                break;
            case 'delete':
                $ret = 'Delete existing ' . $entityName . ' resource';
        }
        return $ret;
    }


}
