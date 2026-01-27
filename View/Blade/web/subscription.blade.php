<div class="max-w-4xl">
    <!-- Page header -->
    <div class="mb-8">
        <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 mb-2">
            <a href="{{ route('hub.billing.index') }}" class="hover:text-gray-700 dark:hover:text-gray-200">Billing</a>
            <core:icon name="chevron-right" class="size-4" />
            <span class="text-gray-900 dark:text-gray-100">Subscription</span>
        </div>
        <h1 class="text-2xl md:text-3xl text-gray-800 dark:text-gray-100 font-bold">Subscription</h1>
        <p class="text-gray-500 dark:text-gray-400 mt-1">Manage your subscription plan</p>
    </div>

    <div class="space-y-6">
        <!-- Current Subscription -->
        <div class="bg-white dark:bg-gray-800 shadow-xs rounded-xl">
            <header class="px-5 py-4 border-b border-gray-100 dark:border-gray-700/60">
                <h2 class="font-semibold text-gray-800 dark:text-gray-100">Current Subscription</h2>
            </header>
            <div class="p-5">
                @if($activeSubscription)
                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                        <div class="space-y-4">
                            <div class="flex items-center gap-3">
                                <span class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                                    {{ $currentPlan }}
                                </span>
                                @if($activeSubscription->cancelled_at)
                                    <core:badge color="amber">Cancelling</core:badge>
                                @else
                                    <core:badge color="green">Active</core:badge>
                                @endif
                                <core:badge color="gray">{{ ucfirst($billingCycle) }}</core:badge>
                            </div>

                            <div class="space-y-2 text-sm">
                                @if($activeSubscription->cancelled_at)
                                    <div class="flex items-center gap-2 text-amber-600 dark:text-amber-400">
                                        <core:icon name="exclamation-triangle" class="size-4" />
                                        <span>Your subscription will end on {{ $nextBillingDate }}</span>
                                    </div>
                                @else
                                    <div class="flex items-center gap-2 text-gray-600 dark:text-gray-400">
                                        <core:icon name="calendar" class="size-4" />
                                        <span>Next billing date: <span class="font-medium text-gray-900 dark:text-gray-100">{{ $nextBillingDate }}</span></span>
                                    </div>
                                    <div class="flex items-center gap-2 text-gray-600 dark:text-gray-400">
                                        <core:icon name="credit-card" class="size-4" />
                                        <span>Next payment: <span class="font-medium text-gray-900 dark:text-gray-100">{{ $this->formatMoney($nextBillingAmount) }}</span></span>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="flex flex-col sm:flex-row gap-3">
                            @if($activeSubscription->cancelled_at)
                                <core:button wire:click="resumeSubscription" variant="primary">
                                    <core:icon name="arrow-path" class="mr-2" />
                                    Resume Subscription
                                </core:button>
                            @else
                                <core:button href="{{ route('hub.billing.change-plan') }}" variant="outline">
                                    <core:icon name="arrows-right-left" class="mr-2" />
                                    Change Plan
                                </core:button>
                                <core:button wire:click="openCancelModal" variant="outline" class="text-red-600 hover:text-red-700 border-red-200 hover:border-red-300 hover:bg-red-50 dark:text-red-400 dark:border-red-800 dark:hover:bg-red-900/20">
                                    Cancel Subscription
                                </core:button>
                            @endif
                        </div>
                    </div>
                @else
                    <div class="text-center py-8">
                        <div class="w-16 h-16 bg-gray-100 dark:bg-gray-700 rounded-full flex items-center justify-center mx-auto mb-4">
                            <core:icon name="credit-card" class="size-8 text-gray-400" />
                        </div>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">No active subscription</h3>
                        <p class="text-gray-500 dark:text-gray-400 mb-6">
                            You're currently on the free plan. Upgrade to unlock premium features.
                        </p>
                        <core:button href="{{ route('pricing') }}" variant="primary">
                            <core:icon name="sparkles" class="mr-2" />
                            View Plans
                        </core:button>
                    </div>
                @endif
            </div>
        </div>

        <!-- Subscription Features -->
        @if($activeSubscription && $activeSubscription->workspacePackage?->package)
            @php
                $package = $activeSubscription->workspacePackage->package;
            @endphp
            <div class="bg-white dark:bg-gray-800 shadow-xs rounded-xl">
                <header class="px-5 py-4 border-b border-gray-100 dark:border-gray-700/60">
                    <h2 class="font-semibold text-gray-800 dark:text-gray-100">Plan Features</h2>
                </header>
                <div class="p-5">
                    @if($package->description)
                        <p class="text-gray-600 dark:text-gray-400 mb-4">{{ $package->description }}</p>
                    @endif
                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach($package->features as $packageFeature)
                            <div class="flex items-center gap-2 text-sm">
                                <core:icon name="check-circle" class="size-5 text-green-500 shrink-0" />
                                <span class="text-gray-700 dark:text-gray-300">
                                    {{ $packageFeature->feature?->name ?? $packageFeature->feature_code }}
                                    @if($packageFeature->limit_type === 'quota' && $packageFeature->limit_value > 0)
                                        <span class="text-gray-500">({{ number_format($packageFeature->limit_value) }})</span>
                                    @elseif($packageFeature->limit_type === 'unlimited')
                                        <span class="text-purple-500">(Unlimited)</span>
                                    @endif
                                </span>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-4 pt-4 border-t border-gray-100 dark:border-gray-700/60">
                        <core:button href="{{ route('hub.usage') }}" variant="ghost" size="sm">
                            <core:icon name="chart-bar" class="mr-2" />
                            View Usage Details
                        </core:button>
                    </div>
                </div>
            </div>
        @endif

        <!-- Subscription History -->
        @if($subscriptionHistory->isNotEmpty())
            <div class="bg-white dark:bg-gray-800 shadow-xs rounded-xl">
                <header class="px-5 py-4 border-b border-gray-100 dark:border-gray-700/60">
                    <h2 class="font-semibold text-gray-800 dark:text-gray-100">Subscription History</h2>
                </header>
                <div class="divide-y divide-gray-100 dark:divide-gray-700/60">
                    @foreach($subscriptionHistory as $subscription)
                        <div class="flex items-center justify-between p-5">
                            <div class="flex items-center gap-4">
                                <div class="shrink-0">
                                    @if($subscription->isActive())
                                        <div class="w-10 h-10 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
                                            <core:icon name="check" class="size-5 text-green-600 dark:text-green-400" />
                                        </div>
                                    @elseif($subscription->cancelled_at)
                                        <div class="w-10 h-10 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
                                            <core:icon name="x-mark" class="size-5 text-gray-400" />
                                        </div>
                                    @else
                                        <div class="w-10 h-10 rounded-full bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center">
                                            <core:icon name="clock" class="size-5 text-amber-600 dark:text-amber-400" />
                                        </div>
                                    @endif
                                </div>
                                <div>
                                    <div class="font-medium text-gray-900 dark:text-gray-100">
                                        {{ $subscription->workspacePackage?->package?->name ?? 'Subscription' }}
                                    </div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                        @if($subscription->current_period_start && $subscription->current_period_end)
                                            {{ $subscription->current_period_start->format('j M Y') }} - {{ $subscription->current_period_end->format('j M Y') }}
                                        @else
                                            Started {{ $subscription->created_at->format('j M Y') }}
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="text-right">
                                @if($subscription->isActive())
                                    @if($subscription->cancelled_at)
                                        <core:badge color="amber">Cancelling</core:badge>
                                    @else
                                        <core:badge color="green">Active</core:badge>
                                    @endif
                                @else
                                    <core:badge color="gray">{{ ucfirst($subscription->status) }}</core:badge>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Need Help -->
        <div class="bg-gray-50 dark:bg-gray-800/50 rounded-xl p-5 text-sm text-gray-600 dark:text-gray-400">
            <div class="flex items-start gap-3">
                <core:icon name="question-mark-circle" class="size-5 text-blue-500 shrink-0" />
                <div>
                    <h3 class="font-medium text-gray-900 dark:text-gray-100 mb-1">Questions about your subscription?</h3>
                    <p>Contact the support team at <a href="mailto:support@host.uk.com" class="text-blue-600 dark:text-blue-400 hover:underline">support@host.uk.com</a> and they'll be happy to help.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Cancel Modal -->
    @if($showCancelModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50" wire:click.self="closeCancelModal">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl max-w-md w-full p-6">
                <div class="text-center mb-6">
                    <div class="w-16 h-16 bg-red-100 dark:bg-red-900/30 rounded-full flex items-center justify-center mx-auto mb-4">
                        <core:icon name="exclamation-triangle" class="size-8 text-red-600 dark:text-red-400" />
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-2">Cancel your subscription?</h3>
                    <p class="text-gray-500 dark:text-gray-400">
                        Your subscription will remain active until {{ $nextBillingDate }}, then your account will be downgraded to the free plan.
                    </p>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Why are you cancelling? (optional)
                    </label>
                    <textarea
                        wire:model="cancelReason"
                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        rows="3"
                        placeholder="Your feedback helps us improve..."
                    ></textarea>
                </div>

                <div class="flex gap-3">
                    <core:button wire:click="closeCancelModal" variant="outline" class="flex-1">
                        Keep Subscription
                    </core:button>
                    <core:button wire:click="cancelSubscription" variant="danger" class="flex-1">
                        Cancel Subscription
                    </core:button>
                </div>
            </div>
        </div>
    @endif
</div>
