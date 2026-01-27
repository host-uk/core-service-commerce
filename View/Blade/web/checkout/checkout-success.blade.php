<div class="min-h-screen flex items-center justify-center px-4">
    <div class="max-w-md w-full text-center">
        {{-- Logo --}}
        <a href="/" class="inline-block mb-8">
            <x-logo class="h-8 w-auto mx-auto" />
        </a>

        @if ($needsAccount)
            {{-- Guest checkout - needs account creation --}}
            <div class="bg-white dark:bg-zinc-800 rounded-2xl shadow-xl border border-zinc-200 dark:border-zinc-700 p-8">
                <div class="w-16 h-16 bg-green-100 dark:bg-green-900/30 rounded-full flex items-center justify-center mx-auto mb-6">
                    <i class="fa-solid fa-check text-2xl text-green-600 dark:text-green-400"></i>
                </div>

                <h1 class="text-2xl font-bold text-zinc-900 dark:text-white mb-2">Payment received</h1>
                <p class="text-zinc-600 dark:text-zinc-400 mb-6">
                    Create your account to access your new subscription.
                </p>

                @if ($errors->any())
                    <div class="mb-6 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-900/50 rounded-lg text-left">
                        <ul class="text-red-600 dark:text-red-400 text-sm space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form wire:submit="createAccount" class="text-left space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Email</label>
                        <input
                            type="email"
                            value="{{ $guestEmail }}"
                            disabled
                            class="w-full px-4 py-3 bg-zinc-100 dark:bg-zinc-700/50 border border-zinc-200 dark:border-zinc-600 rounded-lg text-zinc-500 dark:text-zinc-400"
                        >
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Your name</label>
                        <input
                            wire:model="name"
                            type="text"
                            class="w-full px-4 py-3 bg-white dark:bg-zinc-700 border border-zinc-200 dark:border-zinc-600 rounded-lg text-zinc-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            placeholder="Your name"
                        >
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Password</label>
                        <input
                            wire:model="password"
                            type="password"
                            class="w-full px-4 py-3 bg-white dark:bg-zinc-700 border border-zinc-200 dark:border-zinc-600 rounded-lg text-zinc-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            placeholder="At least 8 characters"
                        >
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Confirm password</label>
                        <input
                            wire:model="password_confirmation"
                            type="password"
                            class="w-full px-4 py-3 bg-white dark:bg-zinc-700 border border-zinc-200 dark:border-zinc-600 rounded-lg text-zinc-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            placeholder="Confirm your password"
                        >
                    </div>

                    <button
                        type="submit"
                        class="w-full px-6 py-3 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 transition-colors"
                        wire:loading.attr="disabled"
                        wire:loading.class="opacity-50 cursor-wait"
                    >
                        <span wire:loading.remove>Create account</span>
                        <span wire:loading>Creating...</span>
                    </button>
                </form>

                <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-4">
                    Already have an account?
                    <a href="{{ route('login') }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">Sign in</a>
                </p>
            </div>
        @elseif ($order)
            @if ($order->isPaid())
                {{-- Success State --}}
                <div class="bg-white dark:bg-zinc-800 rounded-2xl shadow-xl border border-zinc-200 dark:border-zinc-700 p-8">
                    <div class="w-16 h-16 bg-green-100 dark:bg-green-900/30 rounded-full flex items-center justify-center mx-auto mb-6">
                        <i class="fa-solid fa-check text-2xl text-green-600 dark:text-green-400"></i>
                    </div>

                    <h1 class="text-2xl font-bold text-zinc-900 dark:text-white mb-2">Payment successful</h1>
                    <p class="text-zinc-600 dark:text-zinc-400 mb-6">
                        Thank you for your order. Your account has been activated.
                    </p>

                    <div class="bg-zinc-50 dark:bg-zinc-700/50 rounded-lg p-4 mb-6 text-left">
                        <div class="flex justify-between text-sm mb-2">
                            <span class="text-zinc-500 dark:text-zinc-400">Order number</span>
                            <span class="font-medium text-zinc-900 dark:text-white">{{ $order->order_number }}</span>
                        </div>
                        <div class="flex justify-between text-sm mb-2">
                            <span class="text-zinc-500 dark:text-zinc-400">Amount</span>
                            <span class="font-medium text-zinc-900 dark:text-white">
                                {{ app(Mod\Commerce\Services\CommerceService::class)->formatMoney($order->total, $order->currency) }}
                            </span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-zinc-500 dark:text-zinc-400">Email</span>
                            <span class="font-medium text-zinc-900 dark:text-white">{{ $order->billing_email }}</span>
                        </div>
                    </div>

                    <p class="text-sm text-zinc-500 dark:text-zinc-400 mb-6">
                        A confirmation email has been sent to {{ $order->billing_email }}
                    </p>

                    <div class="space-y-3">
                        <a
                            href="{{ route('hub.dashboard') }}"
                            class="block w-full px-6 py-3 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 transition-colors"
                        >
                            Go to Dashboard
                        </a>
                        <a
                            href="/"
                            class="block w-full px-6 py-3 text-zinc-600 dark:text-zinc-400 font-medium hover:text-zinc-900 dark:hover:text-white transition-colors"
                        >
                            Return to homepage
                        </a>
                    </div>
                </div>
            @elseif ($isPending)
                {{-- Pending/Processing State --}}
                <div
                    class="bg-white dark:bg-zinc-800 rounded-2xl shadow-xl border border-zinc-200 dark:border-zinc-700 p-8"
                    wire:poll.5s="checkStatus"
                >
                    <div class="w-16 h-16 bg-amber-100 dark:bg-amber-900/30 rounded-full flex items-center justify-center mx-auto mb-6">
                        <i class="fa-solid fa-clock text-2xl text-amber-600 dark:text-amber-400 animate-pulse"></i>
                    </div>

                    <h1 class="text-2xl font-bold text-zinc-900 dark:text-white mb-2">Processing payment</h1>
                    <p class="text-zinc-600 dark:text-zinc-400 mb-6">
                        Waiting for your payment to be confirmed. This may take a few minutes for crypto payments.
                    </p>

                    <div class="bg-zinc-50 dark:bg-zinc-700/50 rounded-lg p-4 mb-6">
                        <div class="flex justify-between text-sm mb-2">
                            <span class="text-zinc-500 dark:text-zinc-400">Order number</span>
                            <span class="font-medium text-zinc-900 dark:text-white">{{ $order->order_number }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-zinc-500 dark:text-zinc-400">Status</span>
                            <span class="font-medium text-amber-600 dark:text-amber-400">
                                {{ ucfirst($order->status) }}
                            </span>
                        </div>
                    </div>

                    <p class="text-sm text-zinc-500 dark:text-zinc-400">
                        This page will automatically update when payment is confirmed.
                    </p>
                </div>
            @else
                {{-- Failed State --}}
                <div class="bg-white dark:bg-zinc-800 rounded-2xl shadow-xl border border-zinc-200 dark:border-zinc-700 p-8">
                    <div class="w-16 h-16 bg-red-100 dark:bg-red-900/30 rounded-full flex items-center justify-center mx-auto mb-6">
                        <i class="fa-solid fa-xmark text-2xl text-red-600 dark:text-red-400"></i>
                    </div>

                    <h1 class="text-2xl font-bold text-zinc-900 dark:text-white mb-2">Payment issue</h1>
                    <p class="text-zinc-600 dark:text-zinc-400 mb-6">
                        There was a problem with your payment. Please try again or contact support.
                    </p>

                    <div class="space-y-3">
                        <a
                            href="{{ route('checkout.show') }}"
                            class="block w-full px-6 py-3 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 transition-colors"
                        >
                            Try again
                        </a>
                        <a
                            href="mailto:support@host.uk.com"
                            class="block w-full px-6 py-3 text-zinc-600 dark:text-zinc-400 font-medium hover:text-zinc-900 dark:hover:text-white transition-colors"
                        >
                            Contact support
                        </a>
                    </div>
                </div>
            @endif
        @else
            {{-- No Order Found --}}
            <div class="bg-white dark:bg-zinc-800 rounded-2xl shadow-xl border border-zinc-200 dark:border-zinc-700 p-8">
                <div class="w-16 h-16 bg-zinc-100 dark:bg-zinc-700 rounded-full flex items-center justify-center mx-auto mb-6">
                    <i class="fa-solid fa-circle-question text-2xl text-zinc-400"></i>
                </div>

                <h1 class="text-2xl font-bold text-zinc-900 dark:text-white mb-2">Order not found</h1>
                <p class="text-zinc-600 dark:text-zinc-400 mb-6">
                    Couldn't find this order. It may have expired or been completed.
                </p>

                <a
                    href="/"
                    class="block w-full px-6 py-3 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 transition-colors"
                >
                    Return to homepage
                </a>
            </div>
        @endif
    </div>
</div>
