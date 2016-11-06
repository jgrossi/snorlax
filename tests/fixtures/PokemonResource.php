<?php

use Snorlax\RestResource;

/**
 * Class PokemonResource
 */
class PokemonResource extends RestResource
{
    /**
     * @return string
     */
    public function getBaseUri()
    {
        return 'pokemons';
    }

    /**
     * @return array
     */
    public function getActions()
    {
        return [
            'all' => [
                'method' => 'GET',
                'path' => '/',
            ],
            'get' => [
                'method' => 'GET',
                'path' => '/{0}',
            ],
            'capture' => [
                'method' => 'POST',
                'path' => '/',
            ],
            'attack' => [
                'method' => 'PATCH',
                'path' => '/{0}/{1}/{2}',
            ],
        ];
    }
}
