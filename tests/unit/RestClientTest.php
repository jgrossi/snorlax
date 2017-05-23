<?php

use Concat\Http\Middleware\Logger;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use Kevinrob\GuzzleCache\CacheMiddleware;
use Kevinrob\GuzzleCache\Strategy\CacheStrategyInterface;
use Psr\Http\Message\RequestInterface;

use Snorlax\Auth\BearerAuth;

/**
 * Tests for the Snorlax\RestClient class
 */
class RestClientTest extends TestCase
{

    /**
     * @test
     * Verifies that the constructor correctly sets the resources
     */
    public function testResourcesGetter()
    {
        $client = $this->getRestClient();

        $this->assertInstanceOf('PokemonResource', $client->pokemons);
        $this->assertInstanceOf('PokemonResource', $client->getResource('pokemons'));
    }

    /**
     * @test
     * @expectedException \Snorlax\Exception\ResourceNotImplemented
     * @expectedExceptionMessage Resource "digimons" is not implemented
     */
    public function testResourceNotImplementedException()
    {
        $this->getRestClient()->digimons;
    }

    /**
     * @test
     * Verifies that the custom instance passed through the constructor is set
     */
    public function testCustomClientWithInstance()
    {
        $customClient = $this->prophesize(ClientInterface::class);

        $client = $this->getRestClient([
            'custom' => $customClient->reveal(),
            'params' => [],
        ]);

        $this->assertSame($customClient->reveal(), $client->getOriginalClient());
    }

    /**
     * @test
     * Verifies that the custom closure gets executed when client is created
     */
    public function testCustomClientWithClosure()
    {
        $customClient = $this->prophesize(ClientInterface::class);
        $client = $this->getRestClient([
            'custom' => function (array $params) use ($customClient) {
                return $customClient->reveal();
            },
            'params' => [],
        ]);

        $this->assertSame($customClient->reveal(), $client->getOriginalClient());
    }

    /**
     * @test
     * Verifies that the instance is correctly set with cache, debug and log params
     */
    public function testClientWithCacheAndDebugAndLog()
    {
        $customClient = $this->prophesize(ClientInterface::class);

        $client = $this->getRestClient([
            'custom' => $customClient->reveal(),
            'params' => [
                'defaults' => ['debug' => true],
                'cache' => true,
                'log' => true,
            ],
        ]);

        $this->assertSame($customClient->reveal(), $client->getOriginalClient());
    }

    /**
     * @test
     * Verifies that the instance is correctly set with log param
     */
    public function testClientWithLog()
    {
        $customClient = $this->prophesize(ClientInterface::class);

        $client = $this->getRestClient([
            'custom' => $customClient->reveal(),
            'params' => [
                'log' => true,
            ],
        ]);

        $this->assertSame($customClient->reveal(), $client->getOriginalClient());
    }

    /**
     * @test
     * Verifies that the instance is correctly set with cache and debug params
     */
    public function testClientWithCacheAndDebug()
    {
        $customClient = $this->prophesize(ClientInterface::class);

        $client = $this->getRestClient([
            'custom' => $customClient->reveal(),
            'params' => [
                'defaults' => ['debug' => true],
                'cache' => true,
            ],
        ]);

        $this->assertSame($customClient->reveal(), $client->getOriginalClient());
    }

    /**
     * @test
     */
    public function testClientAddsCacheMiddlewareToHandlerStackWhenNoCustomClientProvidedAndCacheEnabled()
    {
        $restClient = $this->getRestClient(['params' => ['cache' => true]]);
        //Get the guzzle client to inspect it.
        $originalClient = $restClient->getOriginalClient();
        $handlerStack = $originalClient->getConfig('handler');
        //The property we need to check is not accessible, make it so.
        $reflection = new ReflectionObject($handlerStack);
        $stackProperty = $reflection->getProperty('stack');
        $stackProperty->setAccessible(true);
        $stack = $stackProperty->getValue($handlerStack);
        //Look for the middleware
        foreach ($stack as $stackItem) {
            if ($stackItem[1] === 'snorlax-cache' && $stackItem[0] instanceof CacheMiddleware) {
                return true;
            }
        }
        //Middleware does not exist on the stack
        $this->fail('No CacheMiddleware named snorlax-cache was found');
    }

    /**
     * @test
     */
    public function testClientAddsLogMiddlewareToHandlerStackWhenNoCustomClientProvidedAndCacheEnabled()
    {
        $restClient = $this->getRestClient(['params' => ['log' => true]]);
        //Get the guzzle client to inspect it.
        $originalClient = $restClient->getOriginalClient();
        $handlerStack = $originalClient->getConfig('handler');
        //The property we need to check is not accessible, make it so.
        $reflection = new ReflectionObject($handlerStack);
        $stackProperty = $reflection->getProperty('stack');
        $stackProperty->setAccessible(true);
        $stack = $stackProperty->getValue($handlerStack);
        //Look for the middleware
        foreach ($stack as $stackItem) {
            if ($stackItem[1] === 'snorlax-logger' && $stackItem[0] instanceof Logger) {
                return true;
            }
        }
        //Middleware does not exist on the stack
        $this->fail('No Logger named snorlax-cache was found');
    }

    /**
     * @test
     */
    public function testRequestPassesArgumentsToGuzzleClient()
    {
        $method = 'PUT';
        $uri = '/endpoint';
        $options = ['body' => '{"key":"value"}', 'retries' => 3];

        $customClient = $this->createMock(ClientInterface::class);
        $customClient->expects($this->once())
            ->method('request')
            ->with($method, $uri, $options);

        $restClient = $this->getRestClient(['custom' => $customClient]);
        $restClient->request($method, $uri, $options);
    }

    /**
     * @test
     */
    public function testRequestAppliesAuthHeadersWhenAuthorizationSet()
    {
        $method = 'PUT';
        $uri = '/endpoint';
        $options = [
            'body' => '{"key":"value"}',
            'headers' => ['key' => 'value'],
            'retries' => 10,
        ];

        $auth = new \Snorlax\Auth\BasicAuth('user', 'password');

        $expectedOptions = $options;
        $expectedOptions['headers']['Authorization'] = $auth->getAuthType() . ' ' . $auth->getCredentials();
        $expectedOptions['retries'] = 10;

        $customClient = $this->prophesize(ClientInterface::class);
        $customClient->request($method, $uri, $expectedOptions)->shouldBeCalled();

        $restClient = $this->getRestClient(['custom' => $customClient->reveal()]);
        $restClient->setAuthorization($auth);
        $restClient->request($method, $uri, $options);
    }

    /**
     * @test
     */
    public function testConfigWithNullLogger()
    {
        $customClient = $this->prophesize(ClientInterface::class);

        $nullLogger = new \Psr\Log\NullLogger();

        $client = $this->getRestClient([
            'custom' => $customClient->reveal(),
            'params' => [
                'defaults' => ['debug' => true],
                'cache' => true,
            ],
            'logger' => $nullLogger,
        ]);

        $this->assertEquals($client->getLogger(), $nullLogger);
    }

    /**
     * @test
     */
    public function testConfigWithDefaultCacheStrategy()
    {
        $customClient = $this->prophesize(ClientInterface::class);
        $cacheStrategy = $this->createMock(CacheStrategyInterface::class);

        $client = $this->getRestClient([
            'custom' => $customClient->reveal(),
            'params' => [
                'defaults' => ['debug' => true],
                'cache' => true,
            ],
            'cacheStrategy' => $cacheStrategy,
        ]);

        $this->assertEquals($client->getcacheStrategy(), $cacheStrategy);
    }

    /**
     * @test
     */
    public function testAutoReconnect()
    {
        $method = 'GET';
        $uri = '/';
        $options = ['retries' => 2];

        $exception = $this->prophesize(ConnectException::class);

        $customClient = $this->prophesize(ClientInterface::class);
        $customClient->request($method, $uri, $options)->willThrow($exception->reveal());
        $customClient->request($method, $uri, ['retries' => 1])->shouldBeCalled();

        $restClient = $this->getRestClient(['custom' => $customClient->reveal()]);
        $restClient->request($method, $uri, $options);
    }

    /**
     * @test
     * @expectedException \GuzzleHttp\Exception\ConnectException
     */
    public function testThrowConnectExceptionWhenRetriesIsOver()
    {
        $method = 'GET';
        $uri = '/';
        $options = ['retries' => 1];

        $request = $this->prophesize(RequestInterface::class);
        $exception = new ConnectException('foo', $request->reveal());

        $customClient = $this->prophesize(ClientInterface::class);
        $customClient->request($method, $uri, $options)->willThrow($exception);
        $customClient->request($method, $uri, ['retries' => 0])->willThrow($exception);

        $restClient = $this->getRestClient(['custom' => $customClient->reveal()]);
        $restClient->request($method, $uri, $options);
    }
}
