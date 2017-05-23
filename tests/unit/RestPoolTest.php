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

        $allParameters = [
            'query' => [
                'userId' => '1',
            ],
        ];

        $getParameters = [
            'parameters' => [
                2
            ],
        ];

        $attackParameters = [
            'parameters' => [
                1,
                2,
                3,
            ],
            'query' => [
                'userId' => '1',
            ],
        ];

        $guzzle = $this->prophesize(ClientInterface::class);
        $guzzle->requestAsync('GET', 'pokemons/', ['query' => ['userId' => '1'], 'retries' => 3])
            ->willReturn($promise->reveal());
        $guzzle->requestAsync('GET', 'pokemons/2', ['retries' => 3])
            ->willReturn($promise->reveal());
        $guzzle->requestAsync('PATCH', 'pokemons/1/2/3', ['query' => ['userId' => '1'], 'retries' => 3])
            ->willReturn($promise->reveal());

        $client = $this->getRestClient([
            'custom' => $guzzle->reveal()
        ]);

        $pool = new RestPool();
        $pool->addResource('pokemonsAll', $client, 'pokemons.all', $allParameters);
        $pool->addResource('pokemonsGet', $client, 'pokemons.get', $getParameters);
        $pool->addResource('pokemonsAttack', $client, 'pokemons.attack', $attackParameters);
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
