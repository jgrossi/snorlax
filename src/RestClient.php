<?php

namespace Snorlax;

use Concat\Http\Middleware\Logger;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\MessageFormatter;
use GuzzleHttp\Middleware;
use Illuminate\Support\Collection;
use Kevinrob\GuzzleCache\CacheMiddleware;
use Kevinrob\GuzzleCache\Strategy\CacheStrategyInterface;
use Monolog\Logger as Monolog;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

use Snorlax\Auth\Authorization;
use Snorlax\Exception\ResourceNotImplemented;
use Snorlax\Exception\TypeMismatch;

/**
 * The REST client.
 * Works as a know it all class that keeps the client and the resources together.
 *
 * @package Snorlax
 */
class RestClient
{
    /**
     * @var \GuzzleHttp\ClientInterface
     */
    private $client = null;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger = null;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $cacheStrategy = null;

    /**
     * @var \Illuminate\Support\Collection
     */
    private $resources = null;

    /**
     * @var \Illuminate\Support\Collection
     */
    private $cache;

    /**
     * @var Authorization
     */
    private $authorization;

    /**
     * Initializes configuration parameters and resources
     *
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->cache = new Collection();

        $this->setClient($config);
        $this->addResources(
            isset($config['resources']) ? $config['resources'] : []
        );
    }

    /**
     * Allows us to use $client->resource so we don't need to call
     * $client->getResource($resource) every time
     *
     * @param $resource
     *
     * @return \Snorlax\Resource
     */
    public function __get($resource)
    {
        return $this->getResource($resource);
    }

    /**
     * Appends the given resources to the ones already being used
     *
     * @param array $resources
     */
    public function addResources(array $resources)
    {
        if (!($this->resources instanceof Collection)) {
            $this->resources = new Collection();
        }

        foreach ($resources as $resource => $class) {
            $this->resources->put($resource, [
                'instance' => null,
                'class' => $class
            ]);
        }
    }

    /**
     * Sets the client according to the given $config array, following the rules:
     * - If no custom client is given, instantiates a new GuzzleHttp\Client
     * - If an instance of GuzzleHttp\ClientInterface is given, we only pass it through
     * - If a closure is given, it gets executed receiving the parameters given
     *
     * @param array $config
     */
    public function setClient(array $config)
    {
        if (isset($config['client'])) {
            $config = $config['client'];
        }

        if (isset($config['logger'])) {
            $this->setLogger($config['logger']);
        }

        if (isset($config['cacheStrategy'])) {
            $this->setCacheStrategy($config['cacheStrategy']);
        }

        $params = isset($config['params']) ? $config['params'] : [];
        $client = null;

        if (isset($config['custom'])) {
            if (is_callable($config['custom'])) {
                $client = $config['custom']($params);
            } elseif ($config['custom'] instanceof ClientInterface) {
                $client = $config['custom'];
            }
        } else {
            $client = $this->createDefaultClient($params);
        }


        $this->client = $client;
    }

    /**
    * Sets the logger client according to the given parameter following the rules:
     * - If an instance of CacheStrategyInterface is given, we only pass it through
     *
     * @param CacheStrategyInterface $cacheStrategy
     */
    public function setCacheStrategy(CacheStrategyInterface $cacheStrategy)
    {
        return $this->cacheStrategy = $cacheStrategy;
    }

    /**
     * Instantiates or returns the cache middleware.
     *
     * @return \Psr\Log\LoggerInterface
     */
    public function getCacheStrategy()
    {
        return $this->cacheStrategy ?: null;
    }

    /**
     * Sets the logger client according to the given parameter following the rules:
     * - If an instance of Psr\Log\LoggerInterface is given, we only pass it through
     *
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger)
    {
        return $this->logger = $logger;
    }

    /**
     * Instantiates or returns the logger driver.
     *
     * @return \Psr\Log\LoggerInterface
     */
    public function getLogger()
    {
        if ($this->logger instanceof LoggerInterface) {
            return $this->logger;
        }

        return new Monolog('Logger');
    }

    /**
     * Creates a new default client based on the given parameters
     *
     * @param array $params
     *
     * @return \GuzzleHttp\Client
     */
    private function createDefaultClient(array $params)
    {
        $stack = HandlerStack::create();

        if (isset($params['cache']) && $params['cache'] === true) {
            $cache = new CacheMiddleware($this->getCacheStrategy());

            $stack->push($cache, 'snorlax-cache');
        }

        if (isset($params['log']) && $params['log'] === true) {
            $logger = new Logger($this->getLogger());
            $logger->setRequestLoggingEnabled();
            $logger->setFormatter(new MessageFormatter(
                'REQ "{method} {target} HTTP/{version}"',
                'RES "{method} {target} HTTP/{version}" {code}'
            ));

            $stack->push($logger, 'snorlax-logger');
        }

        $params = array_merge($params, ['handler' => $stack]);

        return new Client($params);
    }

    /**
     * Instantiates and returns the asked resource.
     *
     * @param string $resource The resource name
     *
     * @throws \Snorlax\Exception\ResourceNotImplemented If the resource is not available
     * @return \Snorlax\Resource The instantiated resource
     */
    public function getResource($resource)
    {
        if ($this->cache->has($resource)) {
            return $this->cache->get($resource);
        }

        if (!$this->resources->has($resource)) {
            throw new ResourceNotImplemented($resource);
        }

        $params = $this->resources->get($resource);
        $instance = $params['instance'];
        if (is_null($instance)) {
            $class = $params['class'];

            $instance = new $class($this);
        }

        return $this->cache[$resource] = $instance;
    }

    /**
     * Changes the authentication method on all the requests made by this client
     *
     * @param \Snorlax\Auth\Authorization $auth The authorizaztion method
     */
    public function setAuthorization(Authorization $auth)
    {
        $this->authorization = $auth;
    }

    /**
     * Returns the internal client
     *
     * @return \GuzzleHttp\ClientInterface
     */
    public function getOriginalClient()
    {
        return $this->client;
    }

    /**
     * @param string $method HTTP method.
     * @param string|\Psr\Http\Message\UriInterface $uri URI object or string.
     * @param array $options Request options to apply.
     *
     * @return ResponseInterface
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function request($method, $uri, $options = [])
    {
        if ($this->authorization !== null) {
            $authHeader = sprintf('%s %s', $this->authorization->getAuthType(), $this->authorization->getCredentials());
            $headers = isset($options['headers']) ? $options['headers'] : [];
            $headers['Authorization'] = $authHeader;
            $options['headers'] = $headers;
        }

        return $this->client->request($method, $uri, $options);
    }
}
