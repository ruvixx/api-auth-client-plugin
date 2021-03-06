<?php

namespace SanchoBBDO\Tests\Guzzle\Plugin\ApiAuth;

use Guzzle\Http\Message\RequestFactory;
use Guzzle\Common\Event;
use SanchoBBDO\Guzzle\Plugin\ApiAuth\ApiAuthPlugin;

class ApiAuthPluginTest extends \PHPUnit_Framework_TestCase
{
    const CANONICAL_STRING = 'text/plain,1B2M2Y8AsgTpgAmY7PhCfg==,/resource.xml?foo=bar&bar=foo,Mon, 23 Jan 1984 03:29:56 GMT';
    const ACCESS_ID = '1044';
    const SECRET_KEY = 'ybqnM8UFztOwDfLOnsLlpUi+weSLvhiA5AigjUmRcWZ9dRSj1cnGWlnGKSAI\n+VT2VcdmQ3F61lfumx133MWcHw==';

    public function setUp()
    {
        $config = array(
            'accessId' => self::ACCESS_ID,
            'secretKey' => self::SECRET_KEY
        );

        $this->apiAuthPlugin = new ApiAuthPlugin($config);
    }

    protected function mockBeforeSendFor($request)
    {
        $event = new Event(array('request' => $request));
        $this->apiAuthPlugin->onRequestBeforeSend($event);
        return $event;
    }

    protected function getRequest()
    {
        return RequestFactory::getInstance()->create(
            'PUT',
            'http://test.co/resource.xml?foo=bar&bar=foo',
            array(
                'Content-type' => "text/plain",
                'Content-MD5' => "1B2M2Y8AsgTpgAmY7PhCfg==",
                'Date' => "Mon, 23 Jan 1984 03:29:56 GMT"
            )
        );
    }

    public function testSubscribesToEvents()
    {
        $events = ApiAuthPlugin::getSubscribedEvents();
        $this->assertArrayHasKey('request.before_send', $events);
    }

    public function testAcceptsConfigurationData()
    {
        // Access the config object
        $class = new \ReflectionClass($this->apiAuthPlugin);
        $property = $class->getProperty('config');
        $property->setAccessible(true);
        $config = $property->getValue($this->apiAuthPlugin);

        $this->assertEquals(self::ACCESS_ID, $config['accessId']);
        $this->assertEquals(self::SECRET_KEY, $config['secretKey']);
    }


    public function testShouldSetTheDateHeaderIfOneIsNotAlreadyPresent()
    {
        $request = RequestFactory::getInstance()->create(
            'PUT',
            'http://test.co/resource.xml?foo=bar&bar=foo',
            array(
                'Content-type' => "text/plain",
                'Content-MD5' => "e59ff97941044f85df5297e1c302d260",
            )
        );

        $event = $this->mockBeforeSendFor($request);
        $this->assertNotEmpty($event['request']->getHeader('Date'));
    }

    public function testMD5HeaderNotAlreadyProvidedShouldCalculateForEmptyString()
    {
        $request = RequestFactory::getInstance()->create(
            'PUT',
            'http://test.co/resource.xml?foo=bar&bar=foo',
            array(
                'Content-type' => "text/plain",
                'Date' => http_date()
            )
        );

        $event = $this->mockBeforeSendFor($request);
        $this->assertEquals(
            (string) $event['request']->getHeader('Content-MD5'),
            '1B2M2Y8AsgTpgAmY7PhCfg=='
        );
    }

    public function testMD5HeaderShouldCalculateForRealContent()
    {
        $request = RequestFactory::getInstance()->create(
            'PUT',
            'http://test.co/resource.xml?foo=bar&bar=foo',
            array(
                'Content-type' => "text/plain",
                'Date' => http_date()
            ),
            "helo\nworld"
        );

        $event = $this->mockBeforeSendFor($request);
        $this->assertEquals(
            (string) $event['request']->getHeader('Content-MD5'),
            'MATnNnvfHYuh9MbanV26yg=='
        );
    }

    public function testMD5HeaderShouldLeaveTheContentMD5AloneIfProvided()
    {
        $request = $this->getRequest();

        $event = $this->mockBeforeSendFor($request);
        $this->assertEquals(
            (string) $event['request']->getHeader('Content-MD5'),
            "1B2M2Y8AsgTpgAmY7PhCfg=="
        );
    }

    public function testShouldGenerateTheProperCanonicalString()
    {
        $request = $this->getRequest();
        $canonicalString = $this->apiAuthPlugin->getCanonicalString($request);
        $this->assertEquals(self::CANONICAL_STRING, $canonicalString);
    }

    public function testGetHMACSignatureGeneratesAValidSignature()
    {
        $request = $this->getRequest();
        $signature = $this->apiAuthPlugin->getHMACSignature($request);
        $this->assertEquals($signature, "pJU5sKxYnd1t83MxJLRsaUBqYSg=");
    }


    public function testShouldSignTheRequest()
    {
        $request = $this->getRequest();
        $signature = $this->apiAuthPlugin->getHMACSignature($request);

        $event = $this->mockBeforeSendFor($request);
        $this->assertEquals(
            (string) $event['request']->getHeader('Authorization'),
            "APIAuth 1044:{$signature}"
        );
    }
}
