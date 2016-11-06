<?php

use Snorlax\RestResource;

/**
 * Class ResourceWithNumberedParameters
 */
class ResourceWithNumberedParameters extends RestResource
{
    /**
     * @return string
     */
    public function getBaseUri()
    {
        return 'endpoint';
    }

    /**
     * @return array
     */
    public function getActions()
    {
        return [
            'get' => [
                'method' => 'GET',
                'path' => '/{1}?id={0}',
            ],
        ];
    }
}
