<?php

declare(strict_types=1);

namespace Core\Mod\Commerce\Exceptions;

use Exception;

/**
 * Exception thrown when a webhook payload fails validation.
 *
 * This security measure prevents malformed or malicious payloads from
 * causing unexpected behaviour in webhook handlers.
 */
class WebhookPayloadValidationException extends Exception
{
    /**
     * Create a new webhook payload validation exception.
     *
     * @param  string  $message  The validation error message
     * @param  string  $gateway  The payment gateway identifier
     * @param  array<string, mixed>  $errors  Specific validation errors
     */
    public function __construct(
        string $message = 'Webhook payload validation failed.',
        protected string $gateway = 'unknown',
        protected array $errors = []
    ) {
        parent::__construct($message);
    }

    /**
     * Get the payment gateway identifier.
     */
    public function getGateway(): string
    {
        return $this->gateway;
    }

    /**
     * Get the specific validation errors.
     *
     * @return array<string, mixed>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Create an exception for invalid JSON.
     */
    public static function invalidJson(string $gateway, string $error): self
    {
        return new self(
            message: "Invalid JSON payload: {$error}",
            gateway: $gateway,
            errors: ['json' => $error]
        );
    }

    /**
     * Create an exception for missing required fields.
     *
     * @param  array<string>  $missingFields
     */
    public static function missingFields(string $gateway, array $missingFields): self
    {
        $fields = implode(', ', $missingFields);

        return new self(
            message: "Missing required fields: {$fields}",
            gateway: $gateway,
            errors: ['missing_fields' => $missingFields]
        );
    }

    /**
     * Create an exception for invalid field types.
     *
     * @param  array<string, array{expected: string, actual: string}>  $typeErrors
     */
    public static function invalidFieldTypes(string $gateway, array $typeErrors): self
    {
        $errorMessages = [];
        foreach ($typeErrors as $field => $types) {
            $errorMessages[] = "{$field} (expected {$types['expected']}, got {$types['actual']})";
        }

        return new self(
            message: 'Invalid field types: '.implode(', ', $errorMessages),
            gateway: $gateway,
            errors: ['type_errors' => $typeErrors]
        );
    }

    /**
     * Create an exception for invalid field values.
     *
     * @param  array<string, string>  $valueErrors
     */
    public static function invalidFieldValues(string $gateway, array $valueErrors): self
    {
        $errorMessages = [];
        foreach ($valueErrors as $field => $error) {
            $errorMessages[] = "{$field}: {$error}";
        }

        return new self(
            message: 'Invalid field values: '.implode(', ', $errorMessages),
            gateway: $gateway,
            errors: ['value_errors' => $valueErrors]
        );
    }
}
