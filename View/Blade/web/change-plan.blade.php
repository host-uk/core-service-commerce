<div class="max-w-6xl">
    <!-- Page header -->
    <div class="mb-8">
        <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 mb-2">
            <a href="{{ route('hub.billing.index') }}" class="hover:text-gray-700 dark:hover:text-gray-200">Billing</a>
            <core:icon name="chevron-right" class="size-4" />
            <a href="{{ route('hub.billing.subscription') }}" class="hover:text-gray-700 dark:hover:text-gray-200">Subscription</a>
            <core:icon name="chevron-right" class="size-4" />
            <span class="text-gray-900 dark:text-gray-100">Change Plan</span>
        </div>
        <h1 class="text-2xl md:text-3xl text-gray-800 dark:text-gray-100 font-bold">Change Plan</h1>
        <p class="text-gray-500 dark:text-gray-400 mt-1">Select a new plan to upgrade or downgrade your subscription</p>
    </div>

    @if(!$currentSubscription)
        <div class="bg-white dark:bg-gray-800 shadow-xs rounded-xl p-8 text-center">
            <div class="w-16 h-16 bg-gray-100 dark:bg-gray-700 rounded-full flex items-center justify-center mx-auto mb-4">
                <core:icon name="credit-card" class="size-8 text-gray-400" />
            </div>
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">No active subscription</h3>
            <p class="text-gray-500 dark:text-gray-400 mb-6">
                You need an active subscription to change plans. Start by choosing a plan below.
            </p>
            <core:button href="{{ route('pricing') }}" variant="primary">
                <core:icon name="sparkles" class="mr-2" />
                View Plans
            </core:button>
        </div>
    @else
        <div class="space-y-6">
            <!-- Billing Cycle Toggle -->
            <div class="bg-white dark:bg-gray-800 shadow-xs rounded-xl p-5">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h3 class="font-medium text-gray-900 dark:text-gray-100">Billing Cycle</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Save up to 20% with annual billing</p>
                    </div>
                    <div class="inline-flex rounded-lg bg-gray-100 dark:bg-gray-700 p-1">
                        <button
                            wire:click="setBillingCycle('monthly')"
                            class="px-4 py-2 text-sm font-medium rounded-md transition-colors {{ $billingCycle === 'monthly' ? 'bg-white dark:bg-gray-600 text-gray-900 dark:text-gray-100 shadow-sm' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200' }}"
                        >
                            Monthly
                        </button>
                        <button
                            wire:click="setBillingCycle('yearly')"
                            class="px-4 py-2 text-sm font-medium rounded-md transition-colors {{ $billingCycle === 'yearly' ? 'bg-white dark:bg-gray-600 text-gray-900 dark:text-gray-100 shadow-sm' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200' }}"
                        >
                            Yearly
                            <span class="ml-1 text-xs text-green-600 dark:text-green-400">Save 20%</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Available Plans -->
            <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                @foreach($availablePackages as $package)
                    <div
                        wire:click="selectPackage('{{ $package->code }}')"
                        class="bg-white dark:bg-gray-800 shadow-xs rounded-xl p-6 cursor-pointer transition-all
                            {{ $selectedPackageCode === $package->code ? 'ring-2 ring-blue-500 dark:ring-blue-400' : 'hover:shadow-md' }}
                            {{ $this->isCurrentPackage($package) ? 'border-2 border-green-500/50' : '' }}"
                    >
                        <div class="flex items-start justify-between mb-4">
                            <div>
                                <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">{{ $package->name }}</h3>
                                @if($this->isCurrentPackage($package))
                                    <core:badge color="green" size="sm" class="mt-1">Current Plan</core:badge>
                                @endif
                            </div>
                            @if($selectedPackageCode === $package->code)
                                <div class="w-6 h-6 bg-blue-500 rounded-full flex items-center justify-center">
                                    <core:icon name="check" class="size-4 text-white" />
                                </div>
                            @endif
                        </div>

                        <div class="mb-4">
                            <span class="text-3xl font-bold text-gray-900 dark:text-gray-100">
                                {{ $this->formatMoney($package->getPrice($billingCycle)) }}
                            </span>
                            <span class="text-gray-500 dark:text-gray-400">/{{ $billingCycle === 'yearly' ? 'year' : 'month' }}</span>
                        </div>

                        @if($package->description)
                            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">{{ $package->description }}</p>
                        @endif

                        <ul class="space-y-2 text-sm">
                            @foreach($package->features->take(5) as $feature)
                                <li class="flex items-center gap-2 text-gray-600 dark:text-gray-400">
                                    <core:icon name="check" class="size-4 text-green-500 shrink-0" />
                                    <span>
                                        {{ $feature->feature?->name ?? $feature->feature_code }}
                                        @if($feature->limit_type === 'quota' && $feature->limit_value > 0)
                                            <span class="text-gray-500">({{ number_format($feature->limit_value) }})</span>
                                        @elseif($feature->limit_type === 'unlimited')
                                            <span class="text-purple-500">(Unlimited)</span>
                                        @endif
                                    </span>
                                </li>
                            @endforeach
                            @if($package->features->count() > 5)
                                <li class="text-blue-600 dark:text-blue-400 text-xs">
                                    +{{ $package->features->count() - 5 }} more features
                                </li>
                            @endif
                        </ul>
                    </div>
                @endforeach
            </div>

            <!-- Error Message -->
            @if($errorMessage)
                <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl p-4">
                    <div class="flex items-center gap-3">
                        <core:icon name="exclamation-circle" class="size-5 text-red-600 dark:text-red-400 shrink-0" />
                        <p class="text-sm text-red-700 dark:text-red-300">{{ $errorMessage }}</p>
                    </div>
                </div>
            @endif

            <!-- Preview & Confirm -->
            @if($selectedPackageCode && !$this->isCurrentPackage($availablePackages->firstWhere('code', $selectedPackageCode)))
                <div class="bg-white dark:bg-gray-800 shadow-xs rounded-xl">
                    <header class="px-5 py-4 border-b border-gray-100 dark:border-gray-700/60">
                        <h2 class="font-semibold text-gray-800 dark:text-gray-100">Plan Change Summary</h2>
                    </header>
                    <div class="p-5">
                        @if(!$showPreview)
                            <div class="text-center py-4">
                                <p class="text-gray-600 dark:text-gray-400 mb-4">
                                    Review the changes before confirming your plan update.
                                </p>
                                <core:button wire:click="preview" variant="primary" :disabled="$isLoading">
                                    @if($isLoading)
                                        <core:icon name="arrow-path" class="mr-2 animate-spin" />
                                        Loading...
                                    @else
                                        <core:icon name="calculator" class="mr-2" />
                                        Calculate Changes
                                    @endif
                                </core:button>
                            </div>
                        @else
                            <div class="space-y-4">
                                <div class="grid gap-4 md:grid-cols-2">
                                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4">
                                        <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">Current Plan</div>
                                        <div class="font-medium text-gray-900 dark:text-gray-100">{{ $previewData['current_plan'] }}</div>
                                        <div class="text-sm text-gray-600 dark:text-gray-400">{{ $this->formatMoney($previewData['current_price']) }}/{{ $billingCycle }}</div>
                                    </div>
                                    <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4">
                                        <div class="text-sm text-blue-600 dark:text-blue-400 mb-1">New Plan</div>
                                        <div class="font-medium text-gray-900 dark:text-gray-100">{{ $previewData['new_plan'] }}</div>
                                        <div class="text-sm text-gray-600 dark:text-gray-400">{{ $this->formatMoney($previewData['new_price']) }}/{{ $billingCycle }}</div>
                                    </div>
                                </div>

                                <div class="border-t border-gray-100 dark:border-gray-700/60 pt-4">
                                    <div class="flex justify-between text-sm mb-2">
                                        <span class="text-gray-600 dark:text-gray-400">Proration credit/charge</span>
                                        <span class="font-medium {{ $previewData['proration_amount'] < 0 ? 'text-green-600 dark:text-green-400' : 'text-gray-900 dark:text-gray-100' }}">
                                            {{ $previewData['proration_amount'] < 0 ? '-' : '' }}{{ $this->formatMoney(abs($previewData['proration_amount'])) }}
                                        </span>
                                    </div>
                                    <div class="flex justify-between text-sm mb-2">
                                        <span class="text-gray-600 dark:text-gray-400">Effective date</span>
                                        <span class="font-medium text-gray-900 dark:text-gray-100">{{ $previewData['effective_date'] }}</span>
                                    </div>
                                    <div class="flex justify-between pt-2 border-t border-gray-100 dark:border-gray-700/60">
                                        <span class="font-medium text-gray-900 dark:text-gray-100">Next billing amount</span>
                                        <span class="font-bold text-lg text-gray-900 dark:text-gray-100">{{ $this->formatMoney($previewData['next_billing_amount']) }}</span>
                                    </div>
                                </div>

                                <div class="flex flex-col sm:flex-row gap-3 pt-4">
                                    <core:button wire:click="resetPreview" variant="outline" class="flex-1">
                                        <core:icon name="arrow-left" class="mr-2" />
                                        Back
                                    </core:button>
                                    <core:button wire:click="confirmChange" variant="primary" class="flex-1" :disabled="$isLoading">
                                        @if($isLoading)
                                            <core:icon name="arrow-path" class="mr-2 animate-spin" />
                                            Processing...
                                        @else
                                            <core:icon name="check" class="mr-2" />
                                            Confirm {{ $previewData['is_upgrade'] ? 'Upgrade' : 'Downgrade' }}
                                        @endif
                                    </core:button>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <!-- Help -->
            <div class="bg-gray-50 dark:bg-gray-800/50 rounded-xl p-5 text-sm text-gray-600 dark:text-gray-400">
                <div class="flex items-start gap-3">
                    <core:icon name="information-circle" class="size-5 text-blue-500 shrink-0" />
                    <div>
                        <h3 class="font-medium text-gray-900 dark:text-gray-100 mb-1">About plan changes</h3>
                        <ul class="list-disc list-inside space-y-1">
                            <li>Upgrades are applied immediately with prorated billing</li>
                            <li>Downgrades take effect at the end of your current billing period</li>
                            <li>Unused time on your current plan is credited to your account</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
