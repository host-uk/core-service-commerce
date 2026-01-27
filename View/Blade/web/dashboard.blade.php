<div class="max-w-4xl">
    <!-- Page header -->
    <div class="mb-8">
        <h1 class="text-2xl md:text-3xl text-gray-800 dark:text-gray-100 font-bold">Billing</h1>
        <p class="text-gray-500 dark:text-gray-400 mt-1">Manage your subscription and payment details</p>
    </div>

    <div class="space-y-6">
        <!-- Current Plan -->
        <div class="bg-white dark:bg-gray-800 shadow-xs rounded-xl">
            <header class="px-5 py-4 border-b border-gray-100 dark:border-gray-700/60">
                <h2 class="font-semibold text-gray-800 dark:text-gray-100">Current Plan</h2>
            </header>
            <div class="p-5">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <span class="text-xl font-bold text-gray-900 dark:text-gray-100">
                                {{ $currentPlan }}
                            </span>
                            @if($activeSubscription)
                                <core:badge size="sm" color="green">Active</core:badge>
                                <core:badge size="sm" color="gray">{{ ucfirst($billingCycle) }}</core:badge>
                            @else
                                <core:badge size="sm" color="gray">Free</core:badge>
                            @endif
                        </div>
                        @if($nextBillingDate)
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">
                                Next billing date: <span class="font-medium text-gray-700 dark:text-gray-300">{{ $nextBillingDate }}</span>
                            </p>
                        @endif
                    </div>
                    <div class="flex gap-3">
                        <core:button href="{{ route('pricing') }}" variant="outline">
                            {{ $activeSubscription ? 'Change Plan' : 'Upgrade' }}
                        </core:button>
                        @if($activeSubscription)
                            <core:button href="{{ route('hub.billing.subscription') }}" variant="outline">
                                Manage
                            </core:button>
                        @endif
                    </div>
                </div>

                @if($nextBillingAmount > 0)
                    <div class="mt-4 pt-4 border-t border-gray-100 dark:border-gray-700/60">
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-gray-500 dark:text-gray-400">Next payment</span>
                            <span class="font-semibold text-gray-900 dark:text-gray-100">
                                {{ $this->formatMoney($nextBillingAmount) }}
                            </span>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Quick Links -->
        <div class="grid gap-4 sm:grid-cols-3">
            <a href="{{ route('hub.billing.invoices') }}" class="bg-white dark:bg-gray-800 shadow-xs rounded-xl p-5 hover:shadow-md transition-shadow group">
                <div class="flex items-center gap-3 mb-2">
                    <div class="w-10 h-10 rounded-lg bg-blue-500/10 flex items-center justify-center">
                        <core:icon name="document-text" class="size-5 text-blue-500" />
                    </div>
                    <h3 class="font-medium text-gray-900 dark:text-gray-100 group-hover:text-blue-600 dark:group-hover:text-blue-400">
                        Invoices
                    </h3>
                </div>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    View and download your invoices
                </p>
            </a>

            <a href="{{ route('hub.billing.payment-methods') }}" class="bg-white dark:bg-gray-800 shadow-xs rounded-xl p-5 hover:shadow-md transition-shadow group">
                <div class="flex items-center gap-3 mb-2">
                    <div class="w-10 h-10 rounded-lg bg-green-500/10 flex items-center justify-center">
                        <core:icon name="credit-card" class="size-5 text-green-500" />
                    </div>
                    <h3 class="font-medium text-gray-900 dark:text-gray-100 group-hover:text-green-600 dark:group-hover:text-green-400">
                        Payment Methods
                    </h3>
                </div>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Manage your payment options
                </p>
            </a>

            <a href="{{ route('hub.usage') }}" class="bg-white dark:bg-gray-800 shadow-xs rounded-xl p-5 hover:shadow-md transition-shadow group">
                <div class="flex items-center gap-3 mb-2">
                    <div class="w-10 h-10 rounded-lg bg-purple-500/10 flex items-center justify-center">
                        <core:icon name="chart-bar" class="size-5 text-purple-500" />
                    </div>
                    <h3 class="font-medium text-gray-900 dark:text-gray-100 group-hover:text-purple-600 dark:group-hover:text-purple-400">
                        Usage
                    </h3>
                </div>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Monitor your feature usage
                </p>
            </a>
        </div>

        <!-- Trees for Agents - Your Impact -->
        @if($treesPlanted > 0 || $activeSubscription)
            <a href="{{ route('trees') }}" class="block bg-gradient-to-r from-green-500/10 to-emerald-500/10 dark:from-green-500/20 dark:to-emerald-500/20 shadow-xs rounded-xl p-5 hover:shadow-md transition-shadow group">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-xl bg-green-500/20 flex items-center justify-center">
                            <svg class="size-6 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0012 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-900 dark:text-gray-100 group-hover:text-green-700 dark:group-hover:text-green-300">
                                Trees for the Future
                            </h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                Your subscription plants real trees
                            </p>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-2xl font-bold text-green-600 dark:text-green-400">
                            {{ number_format($treesPlanted) }}
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            trees planted
                        </div>
                        @if($treesThisYear > 0)
                            <div class="text-xs text-green-600 dark:text-green-400 mt-1">
                                +{{ number_format($treesThisYear) }} this year
                            </div>
                        @endif
                    </div>
                </div>
            </a>
        @endif

        <!-- Recent Invoices -->
        <div class="bg-white dark:bg-gray-800 shadow-xs rounded-xl">
            <header class="px-5 py-4 border-b border-gray-100 dark:border-gray-700/60 flex items-center justify-between">
                <h2 class="font-semibold text-gray-800 dark:text-gray-100">Recent Invoices</h2>
                <core:button href="{{ route('hub.billing.invoices') }}" variant="ghost" size="sm">
                    View all
                </core:button>
            </header>
            <div class="p-5">
                @if($recentInvoices->isEmpty())
                    <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                        <core:icon name="document-text" class="size-8 mx-auto mb-2 opacity-50" />
                        <p>No invoices yet</p>
                        <p class="text-sm mt-1">Invoices will appear here once you make a purchase</p>
                    </div>
                @else
                    <div class="space-y-3">
                        @foreach($recentInvoices as $invoice)
                            <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700/30 rounded-lg">
                                <div class="flex items-center gap-4">
                                    <div class="shrink-0">
                                        @if($invoice->isPaid())
                                            <div class="w-8 h-8 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
                                                <core:icon name="check" class="size-4 text-green-600 dark:text-green-400" />
                                            </div>
                                        @elseif($invoice->isPending())
                                            <div class="w-8 h-8 rounded-full bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center">
                                                <core:icon name="clock" class="size-4 text-amber-600 dark:text-amber-400" />
                                            </div>
                                        @else
                                            <div class="w-8 h-8 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center">
                                                <core:icon name="x-mark" class="size-4 text-red-600 dark:text-red-400" />
                                            </div>
                                        @endif
                                    </div>
                                    <div>
                                        <div class="font-medium text-gray-900 dark:text-gray-100">
                                            {{ $invoice->invoice_number }}
                                        </div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                            {{ $invoice->issued_at?->format('j M Y') }}
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-4">
                                    <div class="text-right">
                                        <div class="font-medium text-gray-900 dark:text-gray-100">
                                            {{ $this->formatMoney($invoice->total) }}
                                        </div>
                                        <div class="text-sm">
                                            @if($invoice->isPaid())
                                                <span class="text-green-600 dark:text-green-400">Paid</span>
                                            @elseif($invoice->isPending())
                                                <span class="text-amber-600 dark:text-amber-400">Pending</span>
                                            @else
                                                <span class="text-red-600 dark:text-red-400">{{ ucfirst($invoice->status) }}</span>
                                            @endif
                                        </div>
                                    </div>
                                    @if($invoice->isPaid())
                                        <core:button href="{{ route('hub.billing.invoices.pdf', $invoice) }}" variant="ghost" size="sm">
                                            <core:icon name="arrow-down-tray" class="size-4" />
                                        </core:button>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <!-- Upcoming Charges -->
        @if($upcomingCharges->isNotEmpty())
            <div class="bg-white dark:bg-gray-800 shadow-xs rounded-xl">
                <header class="px-5 py-4 border-b border-gray-100 dark:border-gray-700/60">
                    <h2 class="font-semibold text-gray-800 dark:text-gray-100">Upcoming Charges</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Subscriptions renewing within the next 30 days</p>
                </header>
                <div class="p-5">
                    <div class="space-y-3">
                        @foreach($upcomingCharges as $subscription)
                            <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700/30 rounded-lg">
                                <div>
                                    <div class="font-medium text-gray-900 dark:text-gray-100">
                                        {{ $subscription->workspacePackage?->package?->name ?? 'Subscription' }}
                                    </div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                        Renews {{ $subscription->current_period_end?->format('j M Y') }}
                                    </div>
                                </div>
                                <div class="text-right">
                                    @php
                                        $package = $subscription->workspacePackage?->package;
                                        $cycle = ($subscription->current_period_start?->diffInDays($subscription->current_period_end) ?? 30) > 32 ? 'yearly' : 'monthly';
                                    @endphp
                                    <div class="font-medium text-gray-900 dark:text-gray-100">
                                        {{ $this->formatMoney($package?->getPrice($cycle) ?? 0) }}
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        <!-- Help Section -->
        <div class="bg-gradient-to-r from-blue-500/10 to-indigo-500/10 dark:from-blue-500/20 dark:to-indigo-500/20 rounded-xl p-6 text-center">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">
                Need help with billing?
            </h3>
            <p class="text-gray-600 dark:text-gray-400 mb-4">
                The support team is here to help with any billing questions
            </p>
            <core:button href="mailto:support@host.uk.com" variant="outline">
                <core:icon name="envelope" class="mr-2" />
                Contact Support
            </core:button>
        </div>
    </div>
</div>
