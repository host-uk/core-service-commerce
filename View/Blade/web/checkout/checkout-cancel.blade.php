<div class="min-h-screen flex items-center justify-center px-4">
    <div class="max-w-md w-full text-center">
        {{-- Logo --}}
        <a href="/" class="inline-block mb-8">
            <x-logo class="h-8 w-auto mx-auto" />
        </a>

        <div class="bg-white dark:bg-zinc-800 rounded-2xl shadow-xl border border-zinc-200 dark:border-zinc-700 p-8">
            <div class="w-16 h-16 bg-zinc-100 dark:bg-zinc-700 rounded-full flex items-center justify-center mx-auto mb-6">
                <i class="fa-solid fa-arrow-rotate-left text-2xl text-zinc-500 dark:text-zinc-400"></i>
            </div>

            <h1 class="text-2xl font-bold text-zinc-900 dark:text-white mb-2">Checkout cancelled</h1>
            <p class="text-zinc-600 dark:text-zinc-400 mb-6">
                Your payment was cancelled. No charges have been made.
            </p>

            @if ($order)
                <p class="text-sm text-zinc-500 dark:text-zinc-400 mb-6">
                    Your order ({{ $order->order_number }}) has been saved. You can resume checkout at any time.
                </p>
            @endif

            <div class="space-y-3">
                <a
                    href="{{ route('checkout.show', $order ? ['plan' => $order->items->first()?->package?->code] : []) }}"
                    class="block w-full px-6 py-3 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 transition-colors"
                >
                    Try again
                </a>
                <a
                    href="/pricing"
                    class="block w-full px-6 py-3 text-zinc-600 dark:text-zinc-400 font-medium hover:text-zinc-900 dark:hover:text-white transition-colors"
                >
                    View pricing options
                </a>
                <a
                    href="/"
                    class="block w-full px-6 py-3 text-zinc-500 dark:text-zinc-500 text-sm hover:text-zinc-700 dark:hover:text-zinc-300 transition-colors"
                >
                    Return to homepage
                </a>
            </div>
        </div>
    </div>
</div>
