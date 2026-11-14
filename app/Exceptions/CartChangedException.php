<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * The cart no longer matches the catalog; it has been refreshed and the customer must confirm again.
 */
class CartChangedException extends RuntimeException
{
    /**
     * @param  list<string>  $messages  Czech, for the customer
     */
    public function __construct(public readonly array $messages)
    {
        parent::__construct('The cart changed: '.implode(' ', $messages));
    }
}
