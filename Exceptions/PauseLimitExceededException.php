<?php

declare(strict_types=1);

namespace Core\Commerce\Exceptions;

use Exception;
use Core\Commerce\Models\Subscription;

/**
 * Exception thrown when a subscription has exceeded its pause cycle limit.
 */
class PauseLimitExceededException extends Exception
{
    public function __construct(
        public readonly Subscription $subscription,
        public readonly int $maxPauseCycles,
        string $message = '',
    ) {
        $message = $message ?: sprintf(
            'Subscription has reached the maximum number of pause cycles (%d).',
            $maxPauseCycles
        );

        parent::__construct($message);
    }
}
