<?php

namespace Snorlax\Auth;

/**
 * Implementation of Authorization via the "Authorization: Bearer" method
 *
 * @package Snorlax\Auth
 */
class BearerAuth implements Authorization
{
    /**
     * @var string
     */
    private $token;

    /**
     * BearerAuth constructor.
     *
     * @param string $token
     */
    public function __construct($token)
    {
        $this->token = $token;
    }

    /**
     * {@inheritDoc}
     */
    public function getCredentials()
    {
        return $this->token;
    }

    /**
     * {@inheritDoc}
     */
    public function getAuthType()
    {
        return 'Bearer';
    }
}
