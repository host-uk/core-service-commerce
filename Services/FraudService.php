<?php

declare(strict_types=1);

namespace Core\Mod\Commerce\Services;

use Core\Mod\Commerce\Data\FraudAssessment;
use Core\Mod\Commerce\Models\Order;
use Core\Mod\Commerce\Models\Payment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Fraud detection and scoring service.
 *
 * Integrates with Stripe Radar for card payments and provides
 * velocity-based and geo-based fraud detection for all payment types.
 */
class FraudService
{
    /**
     * Risk level constants.
     */
    public const RISK_HIGHEST = 'highest';

    public const RISK_ELEVATED = 'elevated';

    public const RISK_NORMAL = 'normal';

    public const RISK_NOT_ASSESSED = 'not_assessed';

    /**
     * Assess fraud risk for an order before checkout.
     *
     * This performs velocity checks and geo-anomaly detection.
     * Stripe Radar assessment happens after payment attempt.
     */
    public function assessOrder(Order $order): FraudAssessment
    {
        if (! config('commerce.fraud.enabled', true)) {
            return FraudAssessment::notAssessed();
        }

        $signals = [];
        $riskLevel = self::RISK_NORMAL;

        // Velocity checks
        if (config('commerce.fraud.velocity.enabled', true)) {
            $velocitySignals = $this->checkVelocity($order);
            $signals = array_merge($signals, $velocitySignals);

            if (! empty($velocitySignals)) {
                $riskLevel = self::RISK_ELEVATED;
            }
        }

        // Geo-anomaly checks
        if (config('commerce.fraud.geo.enabled', true)) {
            $geoSignals = $this->checkGeoAnomalies($order);
            $signals = array_merge($signals, $geoSignals);

            if (! empty($geoSignals)) {
                // High-risk country = highest risk
                if (isset($geoSignals['high_risk_country'])) {
                    $riskLevel = self::RISK_HIGHEST;
                } elseif ($riskLevel !== self::RISK_HIGHEST) {
                    $riskLevel = self::RISK_ELEVATED;
                }
            }
        }

        $assessment = new FraudAssessment(
            riskLevel: $riskLevel,
            signals: $signals,
            source: 'internal',
            shouldBlock: $this->shouldBlockOrder($riskLevel),
            shouldReview: $this->shouldReviewOrder($riskLevel)
        );

        // Log and notify if configured
        $this->logAssessment($order, $assessment);

        return $assessment;
    }

    /**
     * Process fraud signals from Stripe Radar after payment.
     *
     * Called by webhook handlers when receiving payment_intent or charge events.
     */
    public function processStripeRadarOutcome(Payment $payment, array $outcome): FraudAssessment
    {
        if (! config('commerce.fraud.stripe_radar.enabled', true)) {
            return FraudAssessment::notAssessed();
        }

        $signals = [];
        $riskLevel = self::RISK_NORMAL;

        // Extract Stripe Radar risk level
        $stripeRiskLevel = $outcome['risk_level'] ?? null;
        $stripeRiskScore = $outcome['risk_score'] ?? null;
        $networkStatus = $outcome['network_status'] ?? null;
        $sellerMessage = $outcome['seller_message'] ?? null;
        $type = $outcome['type'] ?? null;

        // Map Stripe risk levels
        if ($stripeRiskLevel === 'highest') {
            $riskLevel = self::RISK_HIGHEST;
            $signals['stripe_risk_highest'] = true;
        } elseif ($stripeRiskLevel === 'elevated') {
            $riskLevel = self::RISK_ELEVATED;
            $signals['stripe_risk_elevated'] = true;
        } elseif ($stripeRiskLevel === 'normal' || $stripeRiskLevel === 'not_assessed') {
            $riskLevel = self::RISK_NORMAL;
        }

        // Add risk score if available
        if ($stripeRiskScore !== null) {
            $signals['stripe_risk_score'] = $stripeRiskScore;
        }

        // Check for specific Radar rules triggered
        if (isset($outcome['rule'])) {
            $signals['stripe_rule_triggered'] = $outcome['rule']['id'] ?? 'unknown';
            $signals['stripe_rule_action'] = $outcome['rule']['action'] ?? null;

            // Rule-based blocking overrides score
            if (($outcome['rule']['action'] ?? null) === 'block') {
                $riskLevel = self::RISK_HIGHEST;
            }
        }

        // Network status signals
        if ($networkStatus === 'declined_by_network') {
            $signals['network_declined'] = true;
        }

        $assessment = new FraudAssessment(
            riskLevel: $riskLevel,
            signals: $signals,
            source: 'stripe_radar',
            stripeRiskScore: $stripeRiskScore,
            shouldBlock: $this->shouldBlockPayment($riskLevel),
            shouldReview: $this->shouldReviewPayment($riskLevel)
        );

        // Store assessment on payment if configured
        if (config('commerce.fraud.stripe_radar.store_scores', true)) {
            $this->storeFraudAssessment($payment, $assessment);
        }

        // Log the assessment
        $this->logPaymentAssessment($payment, $assessment);

        return $assessment;
    }

    /**
     * Check velocity-based fraud signals.
     */
    protected function checkVelocity(Order $order): array
    {
        $signals = [];
        $ip = request()->ip();
        $email = $order->billing_email;
        $workspaceId = $order->orderable_id;

        $maxOrdersPerIpHourly = config('commerce.fraud.velocity.max_orders_per_ip_hourly', 5);
        $maxOrdersPerEmailDaily = config('commerce.fraud.velocity.max_orders_per_email_daily', 10);

        // Check orders per IP in the last hour
        if ($ip) {
            $ipKey = "fraud:orders:ip:{$ip}";
            $ipCount = (int) Cache::get($ipKey, 0);

            if ($ipCount >= $maxOrdersPerIpHourly) {
                $signals['velocity_ip_exceeded'] = [
                    'ip' => $ip,
                    'count' => $ipCount,
                    'limit' => $maxOrdersPerIpHourly,
                ];
            }

            // Increment counter (expires in 1 hour)
            Cache::put($ipKey, $ipCount + 1, now()->addHour());
        }

        // Check orders per email in the last 24 hours
        if ($email) {
            $emailKey = 'fraud:orders:email:'.hash('sha256', strtolower($email));
            $emailCount = (int) Cache::get($emailKey, 0);

            if ($emailCount >= $maxOrdersPerEmailDaily) {
                $signals['velocity_email_exceeded'] = [
                    'email_hash' => substr(hash('sha256', $email), 0, 8),
                    'count' => $emailCount,
                    'limit' => $maxOrdersPerEmailDaily,
                ];
            }

            // Increment counter (expires in 24 hours)
            Cache::put($emailKey, $emailCount + 1, now()->addDay());
        }

        // Check failed payments for this workspace in the last hour
        if ($workspaceId) {
            $failedKey = "fraud:failed:workspace:{$workspaceId}";
            $failedCount = (int) Cache::get($failedKey, 0);
            $maxFailed = config('commerce.fraud.velocity.max_failed_payments_hourly', 3);

            if ($failedCount >= $maxFailed) {
                $signals['velocity_failed_exceeded'] = [
                    'workspace_id' => $workspaceId,
                    'failed_count' => $failedCount,
                    'limit' => $maxFailed,
                ];
            }
        }

        return $signals;
    }

    /**
     * Check geo-anomaly fraud signals.
     */
    protected function checkGeoAnomalies(Order $order): array
    {
        $signals = [];
        $billingCountry = $order->billing_address['country'] ?? $order->tax_country ?? null;
        $ipCountry = $this->getIpCountry();

        // Check for country mismatch
        if (config('commerce.fraud.geo.flag_country_mismatch', true)) {
            if ($billingCountry && $ipCountry && $billingCountry !== $ipCountry) {
                $signals['geo_country_mismatch'] = [
                    'billing_country' => $billingCountry,
                    'ip_country' => $ipCountry,
                ];
            }
        }

        // Check for high-risk countries
        $highRiskCountries = config('commerce.fraud.geo.high_risk_countries', []);
        if (! empty($highRiskCountries) && $billingCountry) {
            if (in_array($billingCountry, $highRiskCountries, true)) {
                $signals['high_risk_country'] = $billingCountry;
            }
        }

        return $signals;
    }

    /**
     * Get country code from IP address.
     */
    protected function getIpCountry(): ?string
    {
        $ip = request()->ip();
        if (! $ip || $ip === '127.0.0.1' || str_starts_with($ip, '192.168.')) {
            return null;
        }

        // Use cached geo lookup if available
        $cacheKey = "geo:ip:{$ip}";

        return Cache::remember($cacheKey, now()->addDay(), function () use ($ip) {
            // Try to use Laravel's built-in geo detection if available
            // Otherwise, return null (geo check will be skipped)
            try {
                // This would integrate with a geo-IP service like MaxMind
                // For now, return null as a placeholder
                return null;
            } catch (\Exception $e) {
                Log::warning('Geo-IP lookup failed', ['ip' => $ip, 'error' => $e->getMessage()]);

                return null;
            }
        });
    }

    /**
     * Determine if order should be blocked based on risk level.
     */
    protected function shouldBlockOrder(string $riskLevel): bool
    {
        if (! config('commerce.fraud.actions.auto_block', true)) {
            return false;
        }

        $blockThreshold = config('commerce.fraud.stripe_radar.block_threshold', self::RISK_HIGHEST);

        return $this->riskLevelMeetsThreshold($riskLevel, $blockThreshold);
    }

    /**
     * Determine if order should be flagged for review.
     */
    protected function shouldReviewOrder(string $riskLevel): bool
    {
        $reviewThreshold = config('commerce.fraud.stripe_radar.review_threshold', self::RISK_ELEVATED);

        return $this->riskLevelMeetsThreshold($riskLevel, $reviewThreshold);
    }

    /**
     * Determine if payment should be blocked based on Stripe Radar risk level.
     */
    protected function shouldBlockPayment(string $riskLevel): bool
    {
        if (! config('commerce.fraud.actions.auto_block', true)) {
            return false;
        }

        $blockThreshold = config('commerce.fraud.stripe_radar.block_threshold', self::RISK_HIGHEST);

        return $this->riskLevelMeetsThreshold($riskLevel, $blockThreshold);
    }

    /**
     * Determine if payment should be flagged for review.
     */
    protected function shouldReviewPayment(string $riskLevel): bool
    {
        $reviewThreshold = config('commerce.fraud.stripe_radar.review_threshold', self::RISK_ELEVATED);

        return $this->riskLevelMeetsThreshold($riskLevel, $reviewThreshold);
    }

    /**
     * Check if a risk level meets or exceeds a threshold.
     */
    protected function riskLevelMeetsThreshold(string $riskLevel, string $threshold): bool
    {
        $levels = [
            self::RISK_NOT_ASSESSED => 0,
            self::RISK_NORMAL => 1,
            self::RISK_ELEVATED => 2,
            self::RISK_HIGHEST => 3,
        ];

        return ($levels[$riskLevel] ?? 0) >= ($levels[$threshold] ?? 0);
    }

    /**
     * Store fraud assessment on payment record.
     */
    protected function storeFraudAssessment(Payment $payment, FraudAssessment $assessment): void
    {
        $metadata = $payment->metadata ?? [];
        $metadata['fraud_assessment'] = [
            'risk_level' => $assessment->riskLevel,
            'risk_score' => $assessment->stripeRiskScore,
            'source' => $assessment->source,
            'signals' => $assessment->signals,
            'should_block' => $assessment->shouldBlock,
            'should_review' => $assessment->shouldReview,
            'assessed_at' => now()->toIso8601String(),
        ];

        $payment->update(['metadata' => $metadata]);
    }

    /**
     * Record a failed payment for velocity tracking.
     */
    public function recordFailedPayment(Order $order): void
    {
        $workspaceId = $order->orderable_id;

        if ($workspaceId) {
            $failedKey = "fraud:failed:workspace:{$workspaceId}";
            $failedCount = (int) Cache::get($failedKey, 0);
            Cache::put($failedKey, $failedCount + 1, now()->addHour());
        }
    }

    /**
     * Log fraud assessment.
     */
    protected function logAssessment(Order $order, FraudAssessment $assessment): void
    {
        if (! config('commerce.fraud.actions.log', true)) {
            return;
        }

        if ($assessment->riskLevel === self::RISK_NORMAL && empty($assessment->signals)) {
            return; // Don't log normal orders with no signals
        }

        Log::channel('fraud')->info('Order fraud assessment', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'risk_level' => $assessment->riskLevel,
            'signals' => $assessment->signals,
            'should_block' => $assessment->shouldBlock,
            'should_review' => $assessment->shouldReview,
        ]);
    }

    /**
     * Log payment fraud assessment.
     */
    protected function logPaymentAssessment(Payment $payment, FraudAssessment $assessment): void
    {
        if (! config('commerce.fraud.actions.log', true)) {
            return;
        }

        Log::channel('fraud')->info('Payment fraud assessment (Stripe Radar)', [
            'payment_id' => $payment->id,
            'order_id' => $payment->order_id,
            'risk_level' => $assessment->riskLevel,
            'risk_score' => $assessment->stripeRiskScore,
            'signals' => $assessment->signals,
            'should_block' => $assessment->shouldBlock,
            'should_review' => $assessment->shouldReview,
        ]);

        // Notify admin if high risk and notifications enabled
        if ($assessment->shouldReview && config('commerce.fraud.actions.notify_admin', true)) {
            // This could dispatch a notification job
            // For now, just log at warning level
            Log::channel('fraud')->warning('High-risk payment requires review', [
                'payment_id' => $payment->id,
                'risk_level' => $assessment->riskLevel,
            ]);
        }
    }
}
