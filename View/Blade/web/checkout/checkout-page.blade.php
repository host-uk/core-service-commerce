<div class="min-h-screen bg-zinc-50 dark:bg-zinc-900">
    <div class="max-w-6xl mx-auto px-4 py-8 sm:px-6 lg:px-8">
        {{-- Header with Currency Selector --}}
        <div class="flex items-center justify-between mb-8">
            <a href="/" class="inline-block">
                <x-logo class="h-8 w-auto" />
            </a>
            <div class="flex items-center gap-4">
                <livewire:commerce.web.currency-selector :show-flags="true" :compact="true" />
            </div>
        </div>

        <div class="text-center mb-8">
            <h1 class="text-2xl font-semibold text-zinc-900 dark:text-white">Complete your order</h1>
        </div>

        {{-- Progress Steps --}}
        <div class="flex justify-center mb-8">
            <nav class="flex items-center space-x-4">
                @foreach ([1 => 'Select Plan', 2 => 'Billing Details', 3 => 'Payment'] as $stepNum => $label)
                    <button
                        wire:click="goToStep({{ $stepNum }})"
                        @class([
                            'flex items-center',
                            'cursor-pointer' => $stepNum < $step,
                            'cursor-default' => $stepNum >= $step,
                        ])
                    >
                        <span @class([
                            'w-8 h-8 flex items-center justify-center rounded-full text-sm font-medium',
                            'bg-indigo-600 text-white' => $step === $stepNum,
                            'bg-indigo-100 text-indigo-600 dark:bg-indigo-900 dark:text-indigo-300' => $step > $stepNum,
                            'bg-zinc-200 text-zinc-500 dark:bg-zinc-700 dark:text-zinc-400' => $step < $stepNum,
                        ])>
                            @if ($step > $stepNum)
                                <i class="fa-solid fa-check"></i>
                            @else
                                {{ $stepNum }}
                            @endif
                        </span>
                        <span @class([
                            'ml-2 text-sm font-medium hidden sm:block',
                            'text-indigo-600 dark:text-indigo-400' => $step >= $stepNum,
                            'text-zinc-500 dark:text-zinc-400' => $step < $stepNum,
                        ])>
                            {{ $label }}
                        </span>
                    </button>
                    @if ($stepNum < 3)
                        <div class="w-12 h-px bg-zinc-300 dark:bg-zinc-600"></div>
                    @endif
                @endforeach
            </nav>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            {{-- Main Content --}}
            <div class="lg:col-span-2">
                {{-- Error Display --}}
                @if ($error)
                    <div class="mb-6 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
                        <p class="text-sm text-red-600 dark:text-red-400">{{ $error }}</p>
                    </div>
                @endif

                {{-- Step 1: Plan Selection --}}
                @if ($step === 1)
                    <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700 p-6">
                        <h2 class="text-lg font-semibold text-zinc-900 dark:text-white mb-4">Choose your plan</h2>

                        {{-- Billing Cycle Toggle --}}
                        <div class="flex justify-center mb-6">
                            <div class="inline-flex rounded-lg bg-zinc-100 dark:bg-zinc-700 p-1">
                                <button
                                    wire:click="setBillingCycle('monthly')"
                                    @class([
                                        'px-4 py-2 text-sm font-medium rounded-md transition-colors',
                                        'bg-white dark:bg-zinc-600 text-zinc-900 dark:text-white shadow-sm' => $billingCycle === 'monthly',
                                        'text-zinc-600 dark:text-zinc-300 hover:text-zinc-900 dark:hover:text-white' => $billingCycle !== 'monthly',
                                    ])
                                >
                                    Monthly
                                </button>
                                <button
                                    wire:click="setBillingCycle('yearly')"
                                    @class([
                                        'px-4 py-2 text-sm font-medium rounded-md transition-colors',
                                        'bg-white dark:bg-zinc-600 text-zinc-900 dark:text-white shadow-sm' => $billingCycle === 'yearly',
                                        'text-zinc-600 dark:text-zinc-300 hover:text-zinc-900 dark:hover:text-white' => $billingCycle !== 'yearly',
                                    ])
                                >
                                    Yearly
                                    <span class="ml-1 text-xs text-green-600 dark:text-green-400">Save up to 20%</span>
                                </button>
                            </div>
                        </div>

                        {{-- Package Cards --}}
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            @foreach ($this->packages as $package)
                                <button
                                    wire:click="selectPackage({{ $package->id }})"
                                    @class([
                                        'text-left p-4 rounded-lg border-2 transition-all',
                                        'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20' => $selectedPackageId === $package->id,
                                        'border-zinc-200 dark:border-zinc-600 hover:border-indigo-300 dark:hover:border-indigo-600' => $selectedPackageId !== $package->id,
                                    ])
                                >
                                    <div class="flex items-start justify-between">
                                        <div>
                                            <h3 class="font-semibold text-zinc-900 dark:text-white">{{ $package->name }}</h3>
                                            <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">{{ $package->description }}</p>
                                        </div>
                                        @if ($selectedPackageId === $package->id)
                                            <span class="flex-shrink-0 w-5 h-5 bg-indigo-500 rounded-full flex items-center justify-center">
                                                <i class="fa-solid fa-check text-[0.6rem] text-white"></i>
                                            </span>
                                        @endif
                                    </div>
                                    <div class="mt-4">
                                        <span class="text-2xl font-bold text-zinc-900 dark:text-white">
                                            {{ $this->formatAmount($this->convertToDisplayCurrency($package->getPrice($billingCycle))) }}
                                        </span>
                                        <span class="text-zinc-500 dark:text-zinc-400">/{{ $billingCycle === 'yearly' ? 'year' : 'month' }}</span>
                                    </div>
                                    @if ($package->hasTrial())
                                        <p class="mt-2 text-xs text-green-600 dark:text-green-400">
                                            {{ $package->trial_days }}-day free trial
                                        </p>
                                    @endif
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Step 2: Billing Details --}}
                @if ($step === 2)
                    <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700 p-6">
                        <h2 class="text-lg font-semibold text-zinc-900 dark:text-white mb-4">Billing details</h2>

                        <form wire:submit="proceedToPayment" class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="md:col-span-2">
                                    <label for="billingName" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">
                                        Full name or company name
                                    </label>
                                    <input
                                        type="text"
                                        id="billingName"
                                        wire:model="billingName"
                                        class="w-full rounded-lg border-zinc-300 dark:border-zinc-600 dark:bg-zinc-700 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                                        required
                                    />
                                    @error('billingName')
                                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="md:col-span-2">
                                    <label for="billingEmail" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">
                                        Email address
                                    </label>
                                    <input
                                        type="email"
                                        id="billingEmail"
                                        wire:model="billingEmail"
                                        class="w-full rounded-lg border-zinc-300 dark:border-zinc-600 dark:bg-zinc-700 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                                        required
                                    />
                                    @error('billingEmail')
                                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="md:col-span-2">
                                    <label for="billingAddressLine1" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">
                                        Address line 1
                                    </label>
                                    <input
                                        type="text"
                                        id="billingAddressLine1"
                                        wire:model="billingAddressLine1"
                                        class="w-full rounded-lg border-zinc-300 dark:border-zinc-600 dark:bg-zinc-700 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                                        required
                                    />
                                    @error('billingAddressLine1')
                                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="md:col-span-2">
                                    <label for="billingAddressLine2" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">
                                        Address line 2 <span class="text-zinc-400">(optional)</span>
                                    </label>
                                    <input
                                        type="text"
                                        id="billingAddressLine2"
                                        wire:model="billingAddressLine2"
                                        class="w-full rounded-lg border-zinc-300 dark:border-zinc-600 dark:bg-zinc-700 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                </div>

                                <div>
                                    <label for="billingCity" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">
                                        City
                                    </label>
                                    <input
                                        type="text"
                                        id="billingCity"
                                        wire:model="billingCity"
                                        class="w-full rounded-lg border-zinc-300 dark:border-zinc-600 dark:bg-zinc-700 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                                        required
                                    />
                                    @error('billingCity')
                                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="billingPostalCode" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">
                                        Postal code
                                    </label>
                                    <input
                                        type="text"
                                        id="billingPostalCode"
                                        wire:model="billingPostalCode"
                                        class="w-full rounded-lg border-zinc-300 dark:border-zinc-600 dark:bg-zinc-700 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                                        required
                                    />
                                    @error('billingPostalCode')
                                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="billingState" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">
                                        State/County <span class="text-zinc-400">(optional)</span>
                                    </label>
                                    <input
                                        type="text"
                                        id="billingState"
                                        wire:model="billingState"
                                        class="w-full rounded-lg border-zinc-300 dark:border-zinc-600 dark:bg-zinc-700 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                </div>

                                <div>
                                    <label for="billingCountry" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">
                                        Country
                                    </label>
                                    <select
                                        id="billingCountry"
                                        wire:model.live="billingCountry"
                                        class="w-full rounded-lg border-zinc-300 dark:border-zinc-600 dark:bg-zinc-700 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                                        required
                                    >
                                        @foreach ($this->countries as $code => $name)
                                            <option value="{{ $code }}">{{ $name }}</option>
                                        @endforeach
                                    </select>
                                    @error('billingCountry')
                                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="md:col-span-2">
                                    <label for="taxId" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">
                                        VAT/Tax ID <span class="text-zinc-400">(optional, for business customers)</span>
                                    </label>
                                    <input
                                        type="text"
                                        id="taxId"
                                        wire:model.live="taxId"
                                        placeholder="e.g. GB123456789"
                                        class="w-full rounded-lg border-zinc-300 dark:border-zinc-600 dark:bg-zinc-700 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                    <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                        Enter your VAT number to potentially qualify for reverse charge
                                    </p>
                                </div>
                            </div>

                            <div class="flex justify-between pt-4">
                                <button
                                    type="button"
                                    wire:click="goToStep(1)"
                                    class="px-4 py-2 text-sm font-medium text-zinc-700 dark:text-zinc-300 hover:text-zinc-900 dark:hover:text-white"
                                >
                                    &larr; Back
                                </button>
                                <button
                                    type="submit"
                                    class="px-6 py-2 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                >
                                    Continue to payment &rarr;
                                </button>
                            </div>
                        </form>
                    </div>
                @endif

                {{-- Step 3: Payment --}}
                @if ($step === 3)
                    <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700 p-6">
                        <h2 class="text-lg font-semibold text-zinc-900 dark:text-white mb-4">Choose payment method</h2>

                        <div class="space-y-4">
                            {{-- BTCPay (Primary) --}}
                            @if (config('commerce.gateways.btcpay.enabled'))
                                <button
                                    wire:click="checkout('btcpay')"
                                    wire:loading.attr="disabled"
                                    class="w-full flex items-center justify-between p-4 rounded-lg border-2 border-zinc-200 dark:border-zinc-600 hover:border-indigo-500 dark:hover:border-indigo-400 transition-colors disabled:opacity-50"
                                >
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 bg-orange-100 dark:bg-orange-900/30 rounded-lg flex items-center justify-center mr-3">
                                            <svg class="w-6 h-6 text-orange-500" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M23.638 14.904c-1.602 6.43-8.113 10.34-14.542 8.736C2.67 22.05-1.244 15.525.362 9.105 1.962 2.67 8.475-1.243 14.9.358c6.43 1.605 10.342 8.115 8.738 14.546z"/>
                                            </svg>
                                        </div>
                                        <div class="text-left">
                                            <p class="font-medium text-zinc-900 dark:text-white">Pay with Crypto</p>
                                            <p class="text-sm text-zinc-500 dark:text-zinc-400">Bitcoin, Litecoin, Monero</p>
                                        </div>
                                    </div>
                                    <span wire:loading.remove wire:target="checkout('btcpay')" class="text-indigo-600 dark:text-indigo-400">
                                        <i class="fa-solid fa-arrow-right"></i>
                                    </span>
                                    <span wire:loading wire:target="checkout('btcpay')">
                                        <i class="fa-solid fa-arrows-rotate animate-spin text-indigo-600"></i>
                                    </span>
                                </button>
                            @endif

                            {{-- Stripe (Hidden by default) --}}
                            @if (config('commerce.gateways.stripe.enabled'))
                                <button
                                    wire:click="checkout('stripe')"
                                    wire:loading.attr="disabled"
                                    class="w-full flex items-center justify-between p-4 rounded-lg border-2 border-zinc-200 dark:border-zinc-600 hover:border-indigo-500 dark:hover:border-indigo-400 transition-colors disabled:opacity-50"
                                >
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 bg-purple-100 dark:bg-purple-900/30 rounded-lg flex items-center justify-center mr-3">
                                            <svg class="w-6 h-6 text-purple-500" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M13.976 9.15c-2.172-.806-3.356-1.426-3.356-2.409 0-.831.683-1.305 1.901-1.305 2.227 0 4.515.858 6.09 1.631l.89-5.494C18.252.975 15.697 0 12.165 0 9.667 0 7.589.654 6.104 1.872 4.56 3.147 3.757 4.992 3.757 7.218c0 4.039 2.467 5.76 6.476 7.219 2.585.92 3.445 1.574 3.445 2.583 0 .98-.84 1.545-2.354 1.545-1.875 0-4.965-.921-6.99-2.109l-.9 5.555C5.175 22.99 8.385 24 11.714 24c2.641 0 4.843-.624 6.328-1.813 1.664-1.305 2.525-3.236 2.525-5.732 0-4.128-2.524-5.851-6.591-7.305z"/>
                                            </svg>
                                        </div>
                                        <div class="text-left">
                                            <p class="font-medium text-zinc-900 dark:text-white">Pay with Card</p>
                                            <p class="text-sm text-zinc-500 dark:text-zinc-400">Visa, Mastercard, Amex</p>
                                        </div>
                                    </div>
                                    <span wire:loading.remove wire:target="checkout('stripe')" class="text-indigo-600 dark:text-indigo-400">
                                        <i class="fa-solid fa-arrow-right"></i>
                                    </span>
                                    <span wire:loading wire:target="checkout('stripe')">
                                        <i class="fa-solid fa-arrows-rotate animate-spin text-indigo-600"></i>
                                    </span>
                                </button>
                            @endif
                        </div>

                        <div class="flex justify-between pt-6 border-t border-zinc-200 dark:border-zinc-700 mt-6">
                            <button
                                type="button"
                                wire:click="goToStep(2)"
                                class="px-4 py-2 text-sm font-medium text-zinc-700 dark:text-zinc-300 hover:text-zinc-900 dark:hover:text-white"
                            >
                                &larr; Back
                            </button>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Order Summary Sidebar --}}
            <div class="lg:col-span-1">
                <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700 p-6 sticky top-8">
                    <h3 class="text-lg font-semibold text-zinc-900 dark:text-white mb-4">Order summary</h3>

                    @if ($this->selectedPackage)
                        <div class="space-y-3 mb-4">
                            <div class="flex justify-between">
                                <span class="text-zinc-600 dark:text-zinc-400">{{ $this->selectedPackage->name }}</span>
                                <span class="text-zinc-900 dark:text-white">
                                    {{ $this->formatAmount($this->subtotal) }}
                                </span>
                            </div>
                            <div class="text-sm text-zinc-500 dark:text-zinc-400">
                                Billed {{ $billingCycle === 'yearly' ? 'annually' : 'monthly' }}
                            </div>

                            @if ($this->setupFee > 0)
                                <div class="flex justify-between">
                                    <span class="text-zinc-600 dark:text-zinc-400">Setup fee</span>
                                    <span class="text-zinc-900 dark:text-white">
                                        {{ $this->formatAmount($this->setupFee) }}
                                    </span>
                                </div>
                            @endif

                            @if ($this->discount > 0)
                                <div class="flex justify-between text-green-600 dark:text-green-400">
                                    <span>Discount</span>
                                    <span>-{{ $this->formatAmount($this->discount) }}</span>
                                </div>
                            @endif

                            @if ($this->taxAmount > 0)
                                <div class="flex justify-between">
                                    <span class="text-zinc-600 dark:text-zinc-400">
                                        Tax ({{ number_format($this->taxRate, 0) }}%)
                                    </span>
                                    <span class="text-zinc-900 dark:text-white">
                                        {{ $this->formatAmount($this->taxAmount) }}
                                    </span>
                                </div>
                            @endif
                        </div>

                        <div class="border-t border-zinc-200 dark:border-zinc-700 pt-4 mb-4">
                            <div class="flex justify-between text-lg font-semibold">
                                <span class="text-zinc-900 dark:text-white">Total</span>
                                <span class="text-zinc-900 dark:text-white">
                                    {{ $this->formatAmount($this->total) }}
                                </span>
                            </div>
                            @if ($displayCurrency !== $this->baseCurrency)
                                <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">
                                    Approx. {{ app(\Core\Commerce\Services\CurrencyService::class)->format($this->baseTotal, $this->baseCurrency) }}
                                    at current rates
                                </p>
                            @endif
                        </div>

                        {{-- Coupon Code --}}
                        <div class="border-t border-zinc-200 dark:border-zinc-700 pt-4">
                            @if ($this->appliedCoupon)
                                <div class="flex items-center justify-between bg-green-50 dark:bg-green-900/20 p-3 rounded-lg">
                                    <div>
                                        <p class="text-sm font-medium text-green-700 dark:text-green-300">
                                            {{ $this->appliedCoupon->code }}
                                        </p>
                                        <p class="text-xs text-green-600 dark:text-green-400">{{ $couponSuccess }}</p>
                                    </div>
                                    <button
                                        wire:click="removeCoupon"
                                        class="text-green-600 hover:text-green-800 dark:text-green-400"
                                    >
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                </div>
                            @else
                                <div class="flex gap-2">
                                    <input
                                        type="text"
                                        wire:model="couponCode"
                                        placeholder="Coupon code"
                                        class="flex-1 rounded-lg border-zinc-300 dark:border-zinc-600 dark:bg-zinc-700 dark:text-white text-sm"
                                    />
                                    <button
                                        wire:click="applyCoupon"
                                        class="px-3 py-2 text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 rounded-lg"
                                    >
                                        Apply
                                    </button>
                                </div>
                                @if ($couponError)
                                    <p class="mt-2 text-sm text-red-500">{{ $couponError }}</p>
                                @endif
                            @endif
                        </div>
                    @else
                        <p class="text-zinc-500 dark:text-zinc-400">Select a plan to see pricing</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
