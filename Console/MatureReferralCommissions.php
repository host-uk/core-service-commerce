<?php

declare(strict_types=1);

namespace Core\Commerce\Console;

use Illuminate\Console\Command;
use Core\Commerce\Services\ReferralService;

/**
 * Mature referral commissions that are past their maturation date.
 *
 * Should be run daily via scheduler.
 */
class MatureReferralCommissions extends Command
{
    protected $signature = 'commerce:mature-commissions';

    protected $description = 'Mature referral commissions that are past their maturation date';

    public function handle(ReferralService $referralService): int
    {
        $count = $referralService->matureReadyCommissions();

        $this->info("Matured {$count} commissions.");

        return self::SUCCESS;
    }
}
