<admin:module title="{{ __('commerce::commerce.orders.title') }}" subtitle="{{ __('commerce::commerce.orders.subtitle') }}">
    <admin:flash />

    <admin:filter-bar cols="5">
        <admin:search model="search" placeholder="{{ __('commerce::commerce.orders.search_placeholder') }}" />
        <admin:filter model="statusFilter" :options="$this->statuses" placeholder="{{ __('commerce::commerce.orders.all_statuses') }}" />
        <admin:filter model="typeFilter" :options="$this->types" placeholder="{{ __('commerce::commerce.orders.all_types') }}" />
        <admin:filter model="dateRange" :options="$this->dateRangeOptions" placeholder="{{ __('commerce::commerce.orders.all_time') }}" />
        <admin:clear-filters :fields="['search', 'statusFilter', 'typeFilter', 'dateRange', 'workspaceFilter']" />
    </admin:filter-bar>

    <admin:manager-table
        :columns="$this->tableColumns"
        :rows="$this->tableRows"
        :rowIds="$this->tableRowIds"
        :pagination="$this->orders"
        :selectable="true"
        :selected="$selected"
        empty="{{ __('commerce::commerce.orders.empty') }}"
        emptyIcon="shopping-cart"
    >
        <x-slot:bulkActions>
            <flux:button wire:click="exportSelected" size="sm" icon="arrow-down-tray">{{ __('commerce::commerce.bulk.export') }}</flux:button>
            <flux:dropdown>
                <flux:button size="sm" icon="pencil" icon-trailing="chevron-down">{{ __('commerce::commerce.bulk.change_status') }}</flux:button>
                <flux:menu>
                    @foreach ($this->statuses as $status => $label)
                        <flux:menu.item wire:click="bulkUpdateStatus('{{ $status }}')">{{ $label }}</flux:menu.item>
                    @endforeach
                </flux:menu>
            </flux:dropdown>
        </x-slot:bulkActions>
    </admin:manager-table>

    {{-- Order Detail Modal --}}
    <core:modal wire:model="showDetailModal" class="max-w-3xl">
        @if ($selectedOrder)
            @php
                $statusColor = match($selectedOrder->status) {
                    'paid', 'completed' => 'green',
                    'pending' => 'amber',
                    'processing' => 'blue',
                    'failed', 'cancelled', 'refunded' => 'red',
                    default => 'zinc',
                };
            @endphp

            <core:heading size="lg">{{ __('commerce::commerce.table.order') }} {{ $selectedOrder->order_number }}</core:heading>

            <div class="mt-4 space-y-6">
                {{-- Order Summary Card --}}
                <flux:card class="bg-zinc-50 dark:bg-zinc-800">
                    <flux:heading size="sm" class="mb-3">{{ __('commerce::commerce.orders.detail.summary') }}</flux:heading>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <flux:text size="sm" class="text-zinc-500">{{ __('commerce::commerce.orders.detail.status') }}</flux:text>
                            <flux:badge :color="$statusColor">{{ ucfirst($selectedOrder->status) }}</flux:badge>
                        </div>
                        <div>
                            <flux:text size="sm" class="text-zinc-500">{{ __('commerce::commerce.orders.detail.type') }}</flux:text>
                            <flux:text class="font-medium">{{ str_replace('_', ' ', ucfirst($selectedOrder->type ?? __('commerce::commerce.status.unknown'))) }}</flux:text>
                        </div>
                        <div>
                            <flux:text size="sm" class="text-zinc-500">{{ __('commerce::commerce.orders.detail.payment_gateway') }}</flux:text>
                            <flux:text class="font-medium">{{ ucfirst($selectedOrder->payment_gateway ?? __('commerce::commerce.status.none')) }}</flux:text>
                        </div>
                        <div>
                            <flux:text size="sm" class="text-zinc-500">{{ __('commerce::commerce.orders.detail.paid_at') }}</flux:text>
                            <flux:text class="font-medium">{{ $selectedOrder->paid_at?->format('d M Y H:i') ?? __('commerce::commerce.orders.detail.not_paid') }}</flux:text>
                        </div>
                    </div>
                </flux:card>

                {{-- Customer Info Card --}}
                <flux:card>
                    <flux:heading size="sm" class="mb-3">{{ __('commerce::commerce.orders.detail.customer') }}</flux:heading>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <flux:text size="sm" class="text-zinc-500">{{ __('commerce::commerce.orders.detail.name') }}</flux:text>
                            <flux:text class="font-medium">{{ $selectedOrder->billing_name ?: $selectedOrder->user?->name }}</flux:text>
                        </div>
                        <div>
                            <flux:text size="sm" class="text-zinc-500">{{ __('commerce::commerce.orders.detail.email') }}</flux:text>
                            <flux:text class="font-medium">{{ $selectedOrder->billing_email ?: $selectedOrder->user?->email }}</flux:text>
                        </div>
                        <div>
                            <flux:text size="sm" class="text-zinc-500">{{ __('commerce::commerce.orders.detail.workspace') }}</flux:text>
                            <flux:text class="font-medium">{{ $selectedOrder->workspace?->name }}</flux:text>
                        </div>
                    </div>
                </flux:card>

                {{-- Order Items Card --}}
                <flux:card>
                    <flux:heading size="sm" class="mb-3">{{ __('commerce::commerce.orders.detail.items') }}</flux:heading>
                    <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @foreach ($selectedOrder->items as $item)
                            <div class="flex items-center justify-between py-3 first:pt-0 last:pb-0">
                                <div>
                                    <flux:text class="font-medium">{{ $item->description ?? $item->package?->name }}</flux:text>
                                    <flux:text size="sm" class="text-zinc-500">
                                        {{ $item->quantity }} x {{ $selectedOrder->currency }} {{ number_format($item->unit_price, 2) }}
                                    </flux:text>
                                </div>
                                <flux:text class="font-medium">
                                    {{ $selectedOrder->currency }} {{ number_format($item->line_total, 2) }}
                                </flux:text>
                            </div>
                        @endforeach
                    </div>
                </flux:card>

                {{-- Order Totals Card --}}
                <flux:card class="bg-zinc-50 dark:bg-zinc-800">
                    <flux:heading size="sm" class="mb-3">{{ __('commerce::commerce.orders.detail.totals') }}</flux:heading>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <flux:text>{{ __('commerce::commerce.orders.detail.subtotal') }}</flux:text>
                            <flux:text>{{ $selectedOrder->currency }} {{ number_format($selectedOrder->subtotal, 2) }}</flux:text>
                        </div>
                        @if ($selectedOrder->discount_amount > 0)
                            <div class="flex justify-between text-green-600">
                                <flux:text class="text-green-600">{{ __('commerce::commerce.orders.detail.discount') }}{{ $selectedOrder->coupon ? ' (' . $selectedOrder->coupon->code . ')' : '' }}</flux:text>
                                <flux:text class="text-green-600">-{{ $selectedOrder->currency }} {{ number_format($selectedOrder->discount_amount, 2) }}</flux:text>
                            </div>
                        @endif
                        @if ($selectedOrder->tax_amount > 0)
                            <div class="flex justify-between">
                                <flux:text>{{ __('commerce::commerce.orders.detail.tax') }} ({{ $selectedOrder->tax_rate }}%)</flux:text>
                                <flux:text>{{ $selectedOrder->currency }} {{ number_format($selectedOrder->tax_amount, 2) }}</flux:text>
                            </div>
                        @endif
                        <flux:separator />
                        <div class="flex justify-between pt-1">
                            <flux:text class="font-bold">{{ __('commerce::commerce.orders.detail.total') }}</flux:text>
                            <flux:text class="font-bold text-lg">{{ $selectedOrder->currency }} {{ number_format($selectedOrder->total, 2) }}</flux:text>
                        </div>
                    </div>
                </flux:card>

                {{-- Invoice Card --}}
                @if ($selectedOrder->invoice)
                    <flux:card>
                        <div class="flex items-center justify-between">
                            <div>
                                <flux:text class="font-medium">{{ __('commerce::commerce.orders.detail.invoice') }} {{ $selectedOrder->invoice->invoice_number }}</flux:text>
                                <flux:badge size="sm" :color="$selectedOrder->invoice->status === 'paid' ? 'green' : 'amber'">{{ ucfirst($selectedOrder->invoice->status) }}</flux:badge>
                            </div>
                            <flux:button size="sm" variant="ghost" icon="document-text">{{ __('commerce::commerce.orders.detail.view_invoice') }}</flux:button>
                        </div>
                    </flux:card>
                @endif
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <flux:button wire:click="openStatusChange({{ $selectedOrder->id }})" variant="ghost" icon="pencil">{{ __('commerce::commerce.orders.update_status.title') }}</flux:button>
                <flux:button wire:click="closeDetailModal">{{ __('commerce::commerce.actions.close') }}</flux:button>
            </div>
        @endif
    </core:modal>

    {{-- Status Update Modal --}}
    <core:modal wire:model="showStatusModal" class="max-w-md">
        @if ($selectedOrder)
            <core:heading size="lg">{{ __('commerce::commerce.orders.update_status.title') }}</core:heading>

            <form wire:submit="updateStatus" class="mt-4 space-y-4">
                <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                    <div class="text-sm text-gray-500">{{ __('commerce::commerce.table.order') }}</div>
                    <div class="font-medium">{{ $selectedOrder->order_number }}</div>
                </div>

                <core:select wire:model="newStatus" label="{{ __('commerce::commerce.orders.update_status.new_status') }}">
                    @foreach (array_keys($this->statuses) as $status)
                        <option value="{{ $status }}">{{ $this->statuses[$status] }}</option>
                    @endforeach
                </core:select>

                <core:textarea wire:model="statusNote" label="{{ __('commerce::commerce.orders.update_status.note') }}" rows="2" placeholder="{{ __('commerce::commerce.orders.update_status.note_placeholder') }}" />

                <div class="flex justify-end gap-2 pt-4">
                    <core:button variant="ghost" wire:click="closeStatusModal">{{ __('commerce::commerce.actions.cancel') }}</core:button>
                    <core:button type="submit" variant="primary">{{ __('commerce::commerce.orders.update_status.title') }}</core:button>
                </div>
            </form>
        @endif
    </core:modal>
</admin:module>
