<div class="max-w-4xl">
    <!-- Page header -->
    <div class="mb-8">
        <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 mb-2">
            <a href="{{ route('hub.billing.index') }}" class="hover:text-gray-700 dark:hover:text-gray-200">Billing</a>
            <core:icon name="chevron-right" class="size-4" />
            <span class="text-gray-900 dark:text-gray-100">Invoices</span>
        </div>
        <h1 class="text-2xl md:text-3xl text-gray-800 dark:text-gray-100 font-bold">Invoices</h1>
        <p class="text-gray-500 dark:text-gray-400 mt-1">View and download your billing history</p>
    </div>

    <div class="space-y-6">
        <!-- Filters -->
        <div class="bg-white dark:bg-gray-800 shadow-xs rounded-xl p-4">
            <div class="flex flex-wrap gap-2">
                <button
                    wire:click="$set('status', 'all')"
                    class="px-4 py-2 text-sm font-medium rounded-lg transition-colors {{ $status === 'all' ? 'bg-gray-900 text-white dark:bg-gray-100 dark:text-gray-900' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600' }}"
                >
                    All
                </button>
                <button
                    wire:click="$set('status', 'paid')"
                    class="px-4 py-2 text-sm font-medium rounded-lg transition-colors {{ $status === 'paid' ? 'bg-gray-900 text-white dark:bg-gray-100 dark:text-gray-900' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600' }}"
                >
                    Paid
                </button>
                <button
                    wire:click="$set('status', 'pending')"
                    class="px-4 py-2 text-sm font-medium rounded-lg transition-colors {{ $status === 'pending' ? 'bg-gray-900 text-white dark:bg-gray-100 dark:text-gray-900' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600' }}"
                >
                    Pending
                </button>
                <button
                    wire:click="$set('status', 'overdue')"
                    class="px-4 py-2 text-sm font-medium rounded-lg transition-colors {{ $status === 'overdue' ? 'bg-gray-900 text-white dark:bg-gray-100 dark:text-gray-900' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600' }}"
                >
                    Overdue
                </button>
            </div>
        </div>

        <!-- Invoices List -->
        <div class="bg-white dark:bg-gray-800 shadow-xs rounded-xl">
            @if($invoices->isEmpty())
                <div class="p-8 text-center text-gray-500 dark:text-gray-400">
                    <core:icon name="document-text" class="size-12 mx-auto mb-3 opacity-50" />
                    <p class="text-lg font-medium">No invoices found</p>
                    <p class="text-sm mt-1">
                        @if($status === 'all')
                            Invoices will appear here once you make a purchase
                        @else
                            No {{ $status }} invoices found
                        @endif
                    </p>
                </div>
            @else
                <div class="divide-y divide-gray-100 dark:divide-gray-700/60">
                    @foreach($invoices as $invoice)
                        <div class="flex items-center justify-between p-5 hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                            <div class="flex items-center gap-4">
                                <div class="shrink-0">
                                    @if($invoice->isPaid())
                                        <div class="w-10 h-10 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
                                            <core:icon name="check" class="size-5 text-green-600 dark:text-green-400" />
                                        </div>
                                    @elseif($invoice->isPending())
                                        <div class="w-10 h-10 rounded-full bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center">
                                            <core:icon name="clock" class="size-5 text-amber-600 dark:text-amber-400" />
                                        </div>
                                    @elseif($invoice->isOverdue())
                                        <div class="w-10 h-10 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center">
                                            <core:icon name="exclamation-triangle" class="size-5 text-red-600 dark:text-red-400" />
                                        </div>
                                    @else
                                        <div class="w-10 h-10 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
                                            <core:icon name="document-text" class="size-5 text-gray-400" />
                                        </div>
                                    @endif
                                </div>
                                <div>
                                    <div class="font-medium text-gray-900 dark:text-gray-100">
                                        {{ $invoice->invoice_number }}
                                    </div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                        {{ $invoice->issued_at?->format('j F Y') }}
                                        @if($invoice->items->isNotEmpty())
                                            <span class="mx-1">&middot;</span>
                                            {{ $invoice->items->first()->name }}
                                            @if($invoice->items->count() > 1)
                                                <span class="text-gray-400">+ {{ $invoice->items->count() - 1 }} more</span>
                                            @endif
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center gap-6">
                                <div class="text-right">
                                    <div class="font-semibold text-gray-900 dark:text-gray-100">
                                        {{ $this->formatMoney($invoice->total, $invoice->currency) }}
                                    </div>
                                    <div class="text-sm">
                                        @if($invoice->isPaid())
                                            <span class="text-green-600 dark:text-green-400">Paid</span>
                                            @if($invoice->paid_at)
                                                <span class="text-gray-400 ml-1">{{ $invoice->paid_at->format('j M') }}</span>
                                            @endif
                                        @elseif($invoice->isOverdue())
                                            <span class="text-red-600 dark:text-red-400">Overdue</span>
                                        @elseif($invoice->isPending())
                                            <span class="text-amber-600 dark:text-amber-400">Pending</span>
                                        @else
                                            <span class="text-gray-500 dark:text-gray-400">{{ ucfirst($invoice->status) }}</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    @if($invoice->isPaid())
                                        <core:button href="{{ route('hub.billing.invoices.pdf', $invoice) }}" variant="ghost" size="sm" title="Download PDF">
                                            <core:icon name="arrow-down-tray" class="size-5" />
                                        </core:button>
                                    @endif
                                    @if($invoice->isPending() || $invoice->isOverdue())
                                        <core:button href="{{ route('checkout.show') }}?invoice={{ $invoice->id }}" variant="primary" size="sm">
                                            Pay now
                                        </core:button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                @if($invoices->hasPages())
                    <div class="px-5 py-4 border-t border-gray-100 dark:border-gray-700/60">
                        {{ $invoices->links() }}
                    </div>
                @endif
            @endif
        </div>

        <!-- Invoice Details Explanation -->
        <div class="bg-gray-50 dark:bg-gray-800/50 rounded-xl p-5 text-sm text-gray-600 dark:text-gray-400">
            <h3 class="font-medium text-gray-900 dark:text-gray-100 mb-2">About your invoices</h3>
            <ul class="space-y-1">
                <li class="flex items-start gap-2">
                    <core:icon name="check-circle" class="size-4 text-green-500 mt-0.5 shrink-0" />
                    <span>Download PDF invoices for your records and accounting</span>
                </li>
                <li class="flex items-start gap-2">
                    <core:icon name="check-circle" class="size-4 text-green-500 mt-0.5 shrink-0" />
                    <span>Invoices include VAT details where applicable</span>
                </li>
                <li class="flex items-start gap-2">
                    <core:icon name="check-circle" class="size-4 text-green-500 mt-0.5 shrink-0" />
                    <span>Need a custom invoice? Contact support for assistance</span>
                </li>
            </ul>
        </div>
    </div>
</div>
