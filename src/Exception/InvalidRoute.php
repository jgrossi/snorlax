<?php

namespace Snorlax\Exception;

use InvalidArgumentException;

/**
 * Class InvalidRoute
 *
 * @package Snorlax\Exception
 */
class InvalidRoute extends InvalidArgumentException
{
    const MESSAGE_TPL = 'Route must match the pattern \'resource.method\'.';

    /**
     * InvalidRoute constructor.
     */
    public function __construct()
    {
        parent::__construct(self::MESSAGE_TPL);
    }
}
