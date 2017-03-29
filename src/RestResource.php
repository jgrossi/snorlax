<?php

namespace Snorlax;

/**
 * The mother class of all resources. Contains methods to make dynamic requests
 * defined by the Resource::getActions() method
 *
 * @package Snorlax
 */
abstract class RestResource
{
    /**
     * @var RestClient
     */
    protected $client;

    /**
     * @var mixed
     */
    protected $last_response;

    /**
     * Initializes the client
     *
     * @param RestClient
     */
    public function __construct(RestClient $client)
    {
        $this->client = $client;
    }

    /**
     * Calls the method contained in the actions of this resource
     *
     * @param string $method
     * @param array $args
     *
     * @return \StdClass The JSON decoded response
     */
    public function __call($method, $args)
    {
        $action = $this->getActions()[$method];
        $uri = $this->getBaseUri().$this->getPath($action, $args);
        $params = $this->getParams($args);
        $request = $this->client->request($action['method'], $uri, $params);

        if ($this->client->getAsync()) {
            $this->client->setAsync(false);

            return $request;
        }

        $this->last_response = $request;

        $response = $this->last_response->getBody();

        return $this->parse($response);
    }

    /**
     * Returns the URI path for the request
     *
     * @param array $action
     * @param array $args
     *
     * @return mixed
     */
    private function getPath(array $action, array $args)
    {
        $pattern = '/{(\d+)}/';
        $callback = function ($matches) use ($args) {
            return $args[$matches[1]];
        };

        return preg_replace_callback($pattern, $callback, $action['path']);
    }

    /**
     * Extracts the params from the arguments passed
     *
     * @param $args array
     *
     * @return array
     */
    private function getParams(array $args)
    {
        if (count($args) == 0) {
            return [];
        }

        $params = array_slice($args, -1)[0];

        return is_array($params) ? $params : [];
    }

    /**
     * Returns the last_response of the last executed request
     *
     * @return mixed
     */
    public function getLastResponse()
    {
        return $this->last_response;
    }

    /**
     * Returns the response parsed, by default as a json-decoded StdObject
     *
     * @param string $response
     *
     * @return string
     */
    protected function parse($response)
    {
        return json_decode($response);
    }

    /**
     * Returns the base URI for every request
     *
     * @return string
     */
    abstract public function getBaseUri();

    /**
     * Returns the actions available for this resource
     *
     * @return array
     */
    abstract public function getActions();
}
