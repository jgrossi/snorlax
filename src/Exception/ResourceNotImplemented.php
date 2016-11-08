<?php

namespace Snorlax\Exception;

use InvalidArgumentException;

/**
 * Class ResourceNotImplemented
 *
 * @package Snorlax\Exception
 */
class ResourceNotImplemented extends InvalidArgumentException
{
    const MESSAGE_TPL = 'Resource "%s" is not implemented';

    /**
     * ResourceNotImplemented constructor.
     *
     * @param string $resource_name
     */
    public function __construct($resource_name)
    {
        $message = sprintf(self::MESSAGE_TPL, $resource_name);

        parent::__construct($message);
    }
}
