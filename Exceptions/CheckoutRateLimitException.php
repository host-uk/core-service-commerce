<?php

declare(strict_types=1);

namespace Core\Mod\Commerce\Exceptions;

use Exception;

/**
 * Exception thrown when checkout rate limit is exceeded.
 *
 * Prevents card testing attacks by limiting checkout session creation.
 */
class CheckoutRateLimitException extends Exception
{
    /**
     * Create a new checkout rate limit exception.
     *
     * @param  string  $message  The error message
     * @param  int  $retryAfter  Seconds until rate limit resets
     */
    public function __construct(
        string $message = 'Too many checkout attempts. Please wait before trying again.',
        protected int $retryAfter = 0
    ) {
        parent::__construct($message);
    }

    /**
     * Get the number of seconds until the rate limit resets.
     */
    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }

    /**
     * Get the number of minutes until the rate limit resets (rounded up).
     */
    public function getRetryAfterMinutes(): int
    {
        return (int) ceil($this->retryAfter / 60);
    }
}
