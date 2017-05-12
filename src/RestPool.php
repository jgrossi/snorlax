<?php

namespace Snorlax;

use GuzzleHttp\Pool;
use GuzzleHttp\Promise;
use Snorlax\Exception\InvalidRoute;

/**
 * The mother class of all pools. Contains methods to the paralell requests happen.
 *
 * @package Snorlax
 */
class RestPool
{
    /**
     * @var RestResource[]
     */
    private $resources = [];

    /**
     * Add new resource to request pool
     *
     * @param string $name
     * @param RestClient $client
     * @param string $route
     * @param array $params
     */
    public function addResource($name, RestClient $client, $route, $params = [])
    {
        $this->resources[$name] = $this->buildRequest($client, $route, $params);
    }

    /**
     * Build a async request using the injected client
     *
     * @param RestClient $client
     * @param string $route
     * @param array $params
     *
     * @return RestClient
     */
    private function buildRequest($client, $route, $params)
    {
        list($resource, $method) = $this->explodeRoute($route);

        if (array_key_exists('parameters', $params)) {
            $parameters = $params['parameters'];
            unset($params['parameters']);

            return call_user_func_array(
                [ $client->setAsync()->{$resource}, $method ],
                array_merge($parameters, [$params])
            );
        }

        return $client->setAsync()->{$resource}->{$method}($params);
    }

    /**
     * Decompose the dotted route into a executable way.
     *
     * @param RestClient $client
     * @param string $route
     * @param array $params
     *
     * @return array
     */
    private function explodeRoute($route)
    {
        $pieces = explode('.', $route);

        if (count($pieces) !== 2) {
            throw new InvalidRoute();
        }

        return $pieces;
    }

    /**
     * Send and parse the request pool
     *
     * @return \StdClass
     */
    public function send()
    {
        $responses = new \StdClass();

        foreach ($this->resources as $key => $promise) {
            $response = $promise->wait();
            $responses->{$key} = $this->parse($response->getBody());
        }

        return $responses;
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
}
