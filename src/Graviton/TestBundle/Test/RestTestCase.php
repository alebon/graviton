<?php

namespace Graviton\TestBundle\Test;

use Graviton\TestBundle\Test\GravitonTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * REST test case
 *
 * Contains additional helpers for testing RESTful servers
 *
 * @todo refactor alot (use overridden client and whatnot)
 *
 * @category GravitonTestBundle
 * @package  Graviton
 * @author   Lucas Bickel <lucas.bickel@swisscom.com>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     http://swisscom.com
 */
class RestTestCase extends GravitonTestCase
{
    /**
     * grab and decode reponse from client
     *
     * @param Object $client client
     *
     * @return Mixed
     *
     * @todo this needs to move into the client somehow
     */
    public function loadJsonFromClient($client)
    {
        return json_decode($client->getResponse()->getContent());
    }

    /**
     * test for content type based on classname based mapping
     *
     * @param String   $contentType Expected Content-Type
     * @param Response $response    Response from client
     *
     * @return void
     */
    public function assertResponseContentType($contentType, Response $response)
    {
        $this->assertEquals(
            $contentType,
            $response->headers->get('Content-Type'),
            'Content-Type mismatch in response'
        );
    }
}
