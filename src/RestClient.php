<?php

namespace Snorlax;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\HandlerStack;
use Illuminate\Support\Collection;
use Kevinrob\GuzzleCache\CacheMiddleware;
use Psr\Http\Message\ResponseInterface;
use Snorlax\Auth\Authorization;
use Snorlax\Exception\ResourceNotImplemented;

/**
 * The REST client.
 * Works as a know it all class that keeps the client and the resources together.
 */
class RestClient
{
    /**
     * @var \GuzzleHttp\ClientInterface
     */
    private $client = null;

    /**
     * @var \Illuminate\Support\Collection
     */
    private $resources = null;

    /**
     * @var \Illuminate\Support\Collection
     */
    private $cache;

    /**
     * @var array
     */
    private $config = [];

    /** @var Authorization */
    private $authorization;

    /**
     * Initializes configuration parameters and resources
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->cache = new Collection();

        $this->setClient($config);
        $this->addResources(
            isset($config['resources']) ? $config['resources'] : []
        );
    }

    /**
     * Allows us to use $client->resource so we don't need to call
     * $client->getResource($resource) everytime
     */
    public function __get($resource)
    {
        return $this->getResource($resource);
    }

    /**
     * Appends the given resources to the ones already being used
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
     * @param array $config
     */
    public function setClient(array $config)
    {
        if (isset($config['client'])) {
            $config = $config['client'];
        }

        $params = isset($config['params']) ? $config['params'] : [];
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
     * Creates a new default client based on the given parameters
     * @param array $params
     * @return \GuzzleHttp\Client
     */
    private function createDefaultClient(array $params)
    {
        $stack = HandlerStack::create();
        if (isset($params['cache']) && $params['cache'] === true) {
            $middleware = new CacheMiddleware();
            $stack->push($middleware, 'snorlax-cache');
        }

        $params = array_merge($params, ['handler' => $stack]);

        return new Client($params);
    }

    /**
     * Instantiates and returns the asked resource.
     * @param string The resource name
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
     * @param \Snorlax\Auth\Authorization $auth The authorizaztion method
     */
    public function setAuthorization(Authorization $auth)
    {
        $this->authorization = $auth;
    }

    /**
     * Returns the internal client
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
