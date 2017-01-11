<?php

class TestCase extends PHPUnit_Framework_TestCase
{
    public function getRestClient(array $clientConfig = [])
    {
        $resources = [
            'pokemons' => PokemonResource::class,
        ];

        return new Snorlax\RestClient([
            'client' => $clientConfig,
            'resources' => $resources,
        ]);
    }
}
