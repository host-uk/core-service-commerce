<?php

declare(strict_types=1);

namespace Core\Mod\Commerce\Exceptions;

use Core\Mod\Commerce\Data\FraudAssessment;
use Exception;

/**
 * Exception thrown when an order is blocked due to high fraud risk.
 *
 * Contains the fraud assessment for logging and support investigation.
 */
class FraudBlockedException extends Exception
{
    /**
     * Create a new fraud blocked exception.
     *
     * @param  string  $message  The user-facing error message (should not expose fraud signals)
     * @param  FraudAssessment  $assessment  The fraud assessment that triggered the block
     */
    public function __construct(
        string $message = 'This order could not be processed. Please contact support if you believe this is an error.',
        protected FraudAssessment $assessment = new FraudAssessment(
            riskLevel: 'highest',
            signals: [],
            source: 'internal',
            shouldBlock: true,
            shouldReview: false
        )
    ) {
        parent::__construct($message);
    }

    /**
     * Get the fraud assessment that triggered the block.
     */
    public function getAssessment(): FraudAssessment
    {
        return $this->assessment;
    }

    /**
     * Get the risk level from the assessment.
     */
    public function getRiskLevel(): string
    {
        return $this->assessment->riskLevel;
    }

    /**
     * Get the fraud signals that were detected.
     */
    public function getSignals(): array
    {
        return $this->assessment->signals;
    }
}
