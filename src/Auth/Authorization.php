<?php

namespace Snorlax\Auth;

/**
 * Contract for authorization methods through the Authorization header on every
 * request on the instantiated client
 *
 * @package Snorlax\Auth
 */
interface Authorization
{
    /**
     * Returns the credentials/token for the Authorization header
     *
     * @return string
     */
    public function getCredentials();

    /**
     * Returns the authorization type, such as "Bearer" or "Basic"
     */
    public function getAuthType();
}
