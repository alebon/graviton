<?php
namespace Graviton\ExceptionBundle\Listener;

use Graviton\ExceptionBundle\Exception\ValidationException;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpFoundation\Response;

/**
 * Listener for validation exceptions
 *
 * @category GravitonExceptionBundle
 * @package  Graviton
 * @author   Manuel Kipfer <manuel.kipfer@swisscom.com>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     http://swisscom.com
 */
class ValidationExceptionListener
{
    /**
     * Service container
     *
     * @var Symfony\Component\DependencyInjection\Container
     */
    private $container;

    /**
     * Handle the exception and send the right response
     *
     * @param GetResponseForExceptionEvent $event Event
     *
     * @return void
     */
    public function onKernelException(GetResponseForExceptionEvent $event)
    {
        if (($exception = $event->getException()) instanceof ValidationException) {
        	$response = $exception->getResponse();
        	
            $serializer = $this->container->get('graviton.rest.serializer');
            $serializerContext = clone $this->container->get('graviton.rest.serializer.serializercontext');

            // Set status code and content
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);
            $response->setContent(
                $serializer->serialize(
                    $exception->getViolations(),
                    'json',
                    $serializerContext
                )
            );

            $event->setResponse($response);
        }
    }

    /**
     * Set the container
     *
     * @param Symfony\Component\DependencyInjection\Container $container Container
     *
     * @return void
     */
    public function setContainer($container)
    {
        $this->container = $container;
    }
}
