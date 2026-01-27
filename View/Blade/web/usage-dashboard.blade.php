<div class="max-w-4xl">
    <!-- Page header -->
    <div class="mb-8">
        <h1 class="text-2xl md:text-3xl text-gray-800 dark:text-gray-100 font-bold">Usage</h1>
        <p class="text-gray-500 dark:text-gray-400 mt-1">Monitor your current period usage and estimated charges</p>
    </div>

    @if(!$usageBillingEnabled)
        <!-- Usage billing disabled -->
        <div class="bg-white dark:bg-gray-800 shadow-xs rounded-xl p-8 text-center">
            <div class="w-16 h-16 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center mx-auto mb-4">
                <core:icon name="chart-bar" class="size-8 text-gray-400 dark:text-gray-500" />
            </div>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">
                Usage billing not enabled
            </h3>
            <p class="text-gray-500 dark:text-gray-400 max-w-md mx-auto">
                Usage-based billing is not currently enabled for your account. Your subscription includes flat-rate pricing.
            </p>
        </div>
    @elseif(!$activeSubscription)
        <!-- No active subscription -->
        <div class="bg-white dark:bg-gray-800 shadow-xs rounded-xl p-8 text-center">
            <div class="w-16 h-16 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center mx-auto mb-4">
                <core:icon name="credit-card" class="size-8 text-gray-400 dark:text-gray-500" />
            </div>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">
                No active subscription
            </h3>
            <p class="text-gray-500 dark:text-gray-400 max-w-md mx-auto mb-4">
                Subscribe to a plan to start tracking your usage.
            </p>
            <core:button href="{{ route('pricing') }}">
                View Plans
            </core:button>
        </div>
    @else
        <div class="space-y-6">
            <!-- Current Period Overview -->
            <div class="bg-white dark:bg-gray-800 shadow-xs rounded-xl">
                <header class="px-5 py-4 border-b border-gray-100 dark:border-gray-700/60">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="font-semibold text-gray-800 dark:text-gray-100">Current Billing Period</h2>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                {{ $periodStart }} - {{ $periodEnd }}
                            </p>
                        </div>
                        <div class="text-right">
                            <div class="text-sm text-gray-500 dark:text-gray-400">Days remaining</div>
                            <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $daysRemaining }}</div>
                        </div>
                    </div>
                </header>

                @if($currentUsage->isEmpty())
                    <div class="p-8 text-center">
                        <core:icon name="chart-bar" class="size-12 mx-auto mb-3 text-gray-300 dark:text-gray-600" />
                        <p class="text-gray-500 dark:text-gray-400">No usage recorded this period</p>
                    </div>
                @else
                    <div class="p-5">
                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach($currentUsage as $usage)
                                <div class="p-4 bg-gray-50 dark:bg-gray-700/30 rounded-lg">
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="text-sm font-medium text-gray-600 dark:text-gray-400">
                                            {{ $usage['meter_name'] }}
                                        </span>
                                        <span class="text-xs text-gray-500 dark:text-gray-500">
                                            {{ $usage['unit_label'] }}
                                        </span>
                                    </div>
                                    <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                                        {{ $this->formatNumber($usage['quantity']) }}
                                    </div>
                                    @if($usage['estimated_charge'] > 0)
                                        <div class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                            Est. charge: {{ $this->formatMoney($usage['estimated_charge'], $usage['currency']) }}
                                        </div>
                                    @endif

                                    @php
                                        $percentage = $this->getUsagePercentage($usage, $usage['included_quota'] ?? null);
                                        $statusColour = $this->getUsageStatusColour($percentage);
                                    @endphp

                                    @if($percentage !== null)
                                        <div class="mt-3">
                                            <div class="flex items-center justify-between text-xs mb-1">
                                                <span class="text-gray-500 dark:text-gray-400">Included quota</span>
                                                <span class="text-{{ $statusColour }}-600 dark:text-{{ $statusColour }}-400 font-medium">
                                                    {{ $percentage }}%
                                                </span>
                                            </div>
                                            <div class="w-full h-2 bg-gray-200 dark:bg-gray-600 rounded-full overflow-hidden">
                                                <div class="h-full bg-{{ $statusColour }}-500 rounded-full transition-all duration-300"
                                                     style="width: {{ min(100, $percentage) }}%">
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <!-- Estimated Charges Summary -->
            @if($estimatedCharges > 0)
                <div class="bg-gradient-to-r from-amber-500/10 to-orange-500/10 dark:from-amber-500/20 dark:to-orange-500/20 shadow-xs rounded-xl p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="font-semibold text-gray-900 dark:text-gray-100">Estimated Usage Charges</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                Based on current period usage. Final charges calculated at period end.
                            </p>
                        </div>
                        <div class="text-right">
                            <div class="text-3xl font-bold text-amber-600 dark:text-amber-400">
                                {{ $this->formatMoney($estimatedCharges) }}
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                + subscription fee
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Usage History -->
            @if($usageHistory->isNotEmpty())
                <div class="bg-white dark:bg-gray-800 shadow-xs rounded-xl">
                    <header class="px-5 py-4 border-b border-gray-100 dark:border-gray-700/60">
                        <h2 class="font-semibold text-gray-800 dark:text-gray-100">Usage History</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Previous billing periods</p>
                    </header>
                    <div class="p-5">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-left text-gray-500 dark:text-gray-400 border-b border-gray-100 dark:border-gray-700/60">
                                        <th class="pb-3 font-medium">Period</th>
                                        <th class="pb-3 font-medium">Meter</th>
                                        <th class="pb-3 font-medium text-right">Quantity</th>
                                        <th class="pb-3 font-medium text-right">Charge</th>
                                        <th class="pb-3 font-medium text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                                    @foreach($usageHistory as $period => $records)
                                        @foreach($records as $index => $record)
                                            <tr>
                                                @if($index === 0)
                                                    <td class="py-3 text-gray-900 dark:text-gray-100 font-medium" rowspan="{{ $records->count() }}">
                                                        {{ $record->period_start->format('M Y') }}
                                                    </td>
                                                @endif
                                                <td class="py-3 text-gray-600 dark:text-gray-400">
                                                    {{ $record->meter->name }}
                                                </td>
                                                <td class="py-3 text-right text-gray-900 dark:text-gray-100">
                                                    {{ $this->formatNumber($record->quantity) }}
                                                    <span class="text-gray-500 dark:text-gray-500 text-xs">{{ $record->meter->unit_label }}</span>
                                                </td>
                                                <td class="py-3 text-right text-gray-900 dark:text-gray-100">
                                                    {{ $this->formatMoney($record->calculateCharge()) }}
                                                </td>
                                                <td class="py-3 text-center">
                                                    @if($record->billed)
                                                        <core:badge size="sm" color="green">Billed</core:badge>
                                                    @else
                                                        <core:badge size="sm" color="gray">Pending</core:badge>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Refresh Button -->
            <div class="text-center">
                <core:button wire:click="refresh" variant="outline" size="sm">
                    <core:icon name="arrow-path" class="size-4 mr-2" wire:loading.class="animate-spin" wire:target="refresh" />
                    Refresh Usage Data
                </core:button>
            </div>

            <!-- Help Section -->
            <div class="bg-gradient-to-r from-blue-500/10 to-indigo-500/10 dark:from-blue-500/20 dark:to-indigo-500/20 rounded-xl p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">
                    Understanding your usage
                </h3>
                <p class="text-gray-600 dark:text-gray-400 mb-4">
                    Usage is tracked throughout your billing period and charged at the end. Some features may include a quota with your subscription, with overage charges applying beyond that limit.
                </p>
                <div class="flex flex-wrap gap-3">
                    <core:button href="{{ route('hub.billing') }}" variant="outline" size="sm">
                        <core:icon name="credit-card" class="size-4 mr-2" />
                        View Billing
                    </core:button>
                    <core:button href="mailto:support@host.uk.com" variant="ghost" size="sm">
                        <core:icon name="question-mark-circle" class="size-4 mr-2" />
                        Need Help?
                    </core:button>
                </div>
            </div>
        </div>
    @endif
</div>
