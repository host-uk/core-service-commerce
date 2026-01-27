<div class="max-w-4xl">
    <!-- Page header -->
    <div class="mb-8">
        <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 mb-2">
            <a href="{{ route('hub.billing.index') }}" class="hover:text-gray-700 dark:hover:text-gray-200">Billing</a>
            <core:icon name="chevron-right" class="size-4" />
            <span class="text-gray-900 dark:text-gray-100">Payment Methods</span>
        </div>
        <h1 class="text-2xl md:text-3xl text-gray-800 dark:text-gray-100 font-bold">Payment Methods</h1>
        <p class="text-gray-500 dark:text-gray-400 mt-1">Manage your saved payment methods</p>
    </div>

    <div class="space-y-6">
        <!-- Payment Methods List -->
        <div class="bg-white dark:bg-gray-800 shadow-xs rounded-xl">
            <header class="px-5 py-4 border-b border-gray-100 dark:border-gray-700/60 flex items-center justify-between">
                <h2 class="font-semibold text-gray-800 dark:text-gray-100">Saved Payment Methods</h2>
                <div class="flex items-center gap-2">
                    <core:button wire:click="addPaymentMethod" variant="primary" size="sm" :disabled="$isAddingMethod">
                        @if($isAddingMethod)
                            <core:icon name="arrow-path" class="mr-2 animate-spin" />
                            Adding...
                        @else
                            <core:icon name="plus" class="mr-2" />
                            Add Card
                        @endif
                    </core:button>
                    @if($paymentMethods->isNotEmpty())
                        <core:button wire:click="openStripePortal" variant="outline" size="sm">
                            <core:icon name="cog-6-tooth" class="mr-2" />
                            Manage in Stripe
                        </core:button>
                    @endif
                </div>
            </header>
            <div class="p-5">
                @if($paymentMethods->isEmpty())
                    <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                        <core:icon name="credit-card" class="size-12 mx-auto mb-3 opacity-50" />
                        <p class="text-lg font-medium">No payment methods saved</p>
                        <p class="text-sm mt-1 mb-4">Add a card to enable automatic subscription renewals</p>
                        <core:button wire:click="addPaymentMethod" variant="primary" :disabled="$isAddingMethod">
                            @if($isAddingMethod)
                                <core:icon name="arrow-path" class="mr-2 animate-spin" />
                                Setting up...
                            @else
                                <core:icon name="plus" class="mr-2" />
                                Add Payment Method
                            @endif
                        </core:button>
                    </div>
                @else
                    <div class="space-y-4">
                        @foreach($paymentMethods as $method)
                            <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700/30 rounded-lg {{ $method->is_default ? 'ring-2 ring-blue-500' : '' }}">
                                <div class="flex items-center gap-4">
                                    <div class="shrink-0">
                                        @if($method->type === 'card')
                                            <div class="w-12 h-8 bg-white dark:bg-gray-600 rounded flex items-center justify-center shadow-sm">
                                                @php
                                                    $brand = strtolower($method->brand ?? 'unknown');
                                                @endphp
                                                @if($brand === 'visa')
                                                    <span class="text-blue-600 font-bold text-xs">VISA</span>
                                                @elseif($brand === 'mastercard')
                                                    <span class="text-orange-500 font-bold text-xs">MC</span>
                                                @elseif($brand === 'amex')
                                                    <span class="text-blue-400 font-bold text-xs">AMEX</span>
                                                @else
                                                    <core:icon name="credit-card" class="size-5 text-gray-400" />
                                                @endif
                                            </div>
                                        @elseif($method->type === 'crypto')
                                            <div class="w-12 h-8 bg-amber-100 dark:bg-amber-900/30 rounded flex items-center justify-center">
                                                <core:icon name="currency-bitcoin" class="size-5 text-amber-600" />
                                            </div>
                                        @else
                                            <div class="w-12 h-8 bg-gray-100 dark:bg-gray-600 rounded flex items-center justify-center">
                                                <core:icon name="banknotes" class="size-5 text-gray-400" />
                                            </div>
                                        @endif
                                    </div>
                                    <div>
                                        <div class="font-medium text-gray-900 dark:text-gray-100">
                                            @if($method->type === 'card')
                                                {{ ucfirst($method->brand ?? 'Card') }} ending in {{ $method->last_four ?? '****' }}
                                            @elseif($method->type === 'crypto')
                                                Cryptocurrency
                                            @else
                                                {{ ucfirst($method->type) }}
                                            @endif
                                        </div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                            @if($method->type === 'card' && $method->exp_month && $method->exp_year)
                                                Expires {{ str_pad($method->exp_month, 2, '0', STR_PAD_LEFT) }}/{{ $method->exp_year }}
                                            @else
                                                Added {{ $method->created_at->format('j M Y') }}
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    @if($this->isExpiringSoon($method->id))
                                        <core:badge color="amber">Expiring soon</core:badge>
                                    @endif
                                    @if($method->is_default)
                                        <core:badge color="blue">Default</core:badge>
                                    @else
                                        <core:button wire:click="setDefault({{ $method->id }})" variant="ghost" size="sm">
                                            Make default
                                        </core:button>
                                    @endif
                                    <core:button
                                        wire:click="removeMethod({{ $method->id }})"
                                        wire:confirm="Are you sure you want to remove this payment method?"
                                        variant="ghost"
                                        size="sm"
                                        class="text-red-600 hover:text-red-700 hover:bg-red-50 dark:text-red-400 dark:hover:text-red-300 dark:hover:bg-red-900/20"
                                    >
                                        <core:icon name="trash" class="size-4" />
                                    </core:button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <!-- Crypto Payments Info -->
        <div class="bg-gradient-to-r from-amber-500/10 to-orange-500/10 dark:from-amber-500/20 dark:to-orange-500/20 rounded-xl p-6">
            <div class="flex items-start gap-4">
                <div class="w-12 h-12 bg-amber-500/20 rounded-lg flex items-center justify-center shrink-0">
                    <core:icon name="currency-bitcoin" class="size-6 text-amber-600" />
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">
                        Pay with Cryptocurrency
                    </h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        Host UK accepts Bitcoin, Litecoin, and Monero through BTCPay Server. Cryptocurrency payments are processed instantly and require no saved payment method.
                    </p>
                    <div class="flex flex-wrap gap-3">
                        <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                            <core:icon name="check-circle" class="size-4 text-green-500" />
                            <span>No KYC required</span>
                        </div>
                        <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                            <core:icon name="check-circle" class="size-4 text-green-500" />
                            <span>Instant confirmation</span>
                        </div>
                        <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                            <core:icon name="check-circle" class="size-4 text-green-500" />
                            <span>Privacy-focused</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Security Note -->
        <div class="bg-gray-50 dark:bg-gray-800/50 rounded-xl p-5 text-sm text-gray-600 dark:text-gray-400">
            <div class="flex items-start gap-3">
                <core:icon name="shield-check" class="size-5 text-green-500 shrink-0" />
                <div>
                    <h3 class="font-medium text-gray-900 dark:text-gray-100 mb-1">Your payment information is secure</h3>
                    <p>Host UK never stores full card numbers. All payment information is encrypted and handled by PCI-compliant payment processors.</p>
                </div>
            </div>
        </div>
    </div>
</div>
