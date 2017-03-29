<?php

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;
use Snorlax\Exception\InvalidRoute;
use Snorlax\RestPool;

/**
 * Tests for the Snorlax\RestPool class
 */
class RestPoolTest extends TestCase
{
    public function testIfResourcesAreBeingPopulatedWithRequests()
    {
        $response = $this->prophesize(Response::class);

        $promise = $this->prophesize(PromiseInterface::class);
        $promise->wait()->willReturn($response->reveal());

        $filteredParameters = [
            'query' => [
                'userId' => '1',
            ],
        ];

        $guzzle = $this->prophesize(ClientInterface::class);
        $guzzle->requestAsync('GET', 'pokemons/', [])
            ->willReturn($promise->reveal());
        $guzzle->requestAsync('GET', 'pokemons/', $filteredParameters)
            ->willReturn($promise->reveal());

        $client = $this->getRestClient([
            'custom' => $guzzle->reveal()
        ]);

        $pool = new RestPool();
        $pool->addResource('pokemonsAll', $client, 'pokemons.all');
        $pool->addResource('pokemonsFiltered', $client, 'pokemons.all', $filteredParameters);
        $pool->send();
    }

    public function testWrongRoutePattern()
    {
        $this->expectException(InvalidRoute::class);

        $guzzle = $this->prophesize(ClientInterface::class);

        $client = $this->getRestClient([
            'custom' => $guzzle->reveal()
        ]);

        $pool = new RestPool();
        $pool->addResource('itWillFail', $client, 'it.will.fail');
    }
}
