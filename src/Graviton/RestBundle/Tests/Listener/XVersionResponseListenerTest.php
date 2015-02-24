<?php

namespace Graviton\RestBundle\Listener;


use Symfony\Component\HttpFoundation\Response;

class XVersionResponseListenerTest extends \PHPUnit_Framework_TestCase
{
    public function testOnKernelResponse()
    {
        $response = new Response();
        $version = '0.1.0-alpha';

        $eventDouble = $this->getMockBuilder('\Symfony\Component\HttpKernel\Event\FilterResponseEvent')
            ->disableOriginalConstructor()
            ->setMethods(array('getResponse', 'isMasterRequest'))
            ->getMock();
        $eventDouble
            ->expects($this->once())
            ->method('getResponse')
            ->will($this->returnValue($response));$eventDouble
            ->expects($this->once())
            ->method('isMasterRequest')
            ->will($this->returnValue(true));

        $loggerDouble = $this->getMockBuilder('\Psr\Log\LoggerInterface')
            ->setMethods(array('warning'))
            ->getMockForAbstractClass();
        $loggerDouble
            ->expects($this->any())
            ->method('warning')
            ->with($this->contains('Unable to extract version from composer.json file'));

        $serviceDouble = $this->getMockBuilder('\Graviton\CoreBundle\Service\CoreUtils')
            ->setMethods(array('getVersion'))
            ->getMock();
        $serviceDouble
            ->expects($this->once())
            ->method('getVersion')
            ->will($this->returnValue('0.1.0-alpha'));

        $listener = new XVersionResponseListener($serviceDouble, $loggerDouble);
        $listener->onKernelResponse($eventDouble);

        $this->assertEquals($version, $response->headers->get('X-VERSION'));
    }

    public function testOnKernelResponseOnSubRequest()
    {
        $response = new Response();

        $eventDouble = $this->getMockBuilder('\Symfony\Component\HttpKernel\Event\FilterResponseEvent')
            ->disableOriginalConstructor()
            ->setMethods(array('isMasterRequest'))
            ->getMock();
        $eventDouble
            ->expects($this->once())
            ->method('isMasterRequest')
            ->will($this->returnValue(false));

        $loggerDouble = $this->getMockForAbstractClass('\Psr\Log\LoggerInterface');
        $serviceDouble = $this->getMock('\Graviton\CoreBundle\Service\CoreUtils');

        $listener = new XVersionResponseListener($serviceDouble, $loggerDouble);
        $listener->onKernelResponse($eventDouble);

        $this->assertNull($response->headers->get('X-VERSION'));
    }

}
