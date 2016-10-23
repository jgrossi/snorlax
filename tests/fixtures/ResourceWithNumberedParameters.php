<?php

use Snorlax\Resource;

class ResourceWithNumberedParameters extends Resource
{
    public function getBaseUri()
    {
        return 'endpoint';
    }

    public function getActions()
    {
        return [
            'get' => [
                'method' => 'GET',
                'path' => '/{1}?id={0}'
            ]
        ];
    }
}
