<admin:module title="{{ __('commerce::commerce.subscriptions.title') }}" subtitle="{{ __('commerce::commerce.subscriptions.subtitle') }}">
    <admin:flash />

    <admin:filter-bar cols="4">
        <admin:search model="search" placeholder="{{ __('commerce::commerce.subscriptions.search_placeholder') }}" />
        <admin:filter model="statusFilter" :options="$this->statuses" placeholder="{{ __('commerce::commerce.subscriptions.all_statuses') }}" />
        <admin:filter model="gatewayFilter" :options="$this->gateways" placeholder="{{ __('commerce::commerce.subscriptions.all_gateways') }}" />
        <admin:clear-filters :fields="['search', 'statusFilter', 'gatewayFilter', 'workspaceFilter']" />
    </admin:filter-bar>

    <admin:manager-table
        :columns="$this->tableColumns"
        :rows="$this->tableRows"
        :rowIds="$this->tableRowIds"
        :pagination="$this->subscriptions"
        :selectable="true"
        :selected="$selected"
        empty="{{ __('commerce::commerce.subscriptions.empty') }}"
        emptyIcon="credit-card"
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
            <flux:button wire:click="bulkExtendPeriod" size="sm" icon="clock">{{ __('commerce::commerce.bulk.extend_period') }}</flux:button>
        </x-slot:bulkActions>
    </admin:manager-table>

    {{-- Subscription Detail Modal --}}
    <core:modal wire:model="showDetailModal" class="max-w-2xl">
        @if ($selectedSubscription)
            @php
                $statusColor = match($selectedSubscription->status) {
                    'active' => 'green',
                    'trialing' => 'blue',
                    'past_due', 'incomplete' => 'amber',
                    'paused' => 'zinc',
                    'cancelled', 'expired' => 'red',
                    default => 'zinc',
                };

                // Calculate billing period progress
                $periodStart = $selectedSubscription->current_period_start;
                $periodEnd = $selectedSubscription->current_period_end;
                $periodProgress = 0;
                $daysRemaining = 0;
                $totalDays = 0;

                if ($periodStart && $periodEnd) {
                    $totalDays = $periodStart->diffInDays($periodEnd);
                    $daysElapsed = $periodStart->diffInDays(now());
                    $daysRemaining = max(0, now()->diffInDays($periodEnd, false));
                    $periodProgress = $totalDays > 0 ? min(100, round(($daysElapsed / $totalDays) * 100)) : 0;
                }
            @endphp

            <core:heading size="lg">{{ __('commerce::commerce.subscriptions.detail.title') }}</core:heading>

            <div class="mt-4 space-y-6">
                {{-- Status Summary Card --}}
                <flux:card class="bg-zinc-50 dark:bg-zinc-800">
                    <flux:heading size="sm" class="mb-3">{{ __('commerce::commerce.subscriptions.detail.summary') }}</flux:heading>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <flux:text size="sm" class="text-zinc-500">{{ __('commerce::commerce.subscriptions.detail.status') }}</flux:text>
                            <flux:badge :color="$statusColor">{{ ucfirst($selectedSubscription->status) }}</flux:badge>
                        </div>
                        <div>
                            <flux:text size="sm" class="text-zinc-500">{{ __('commerce::commerce.subscriptions.detail.gateway') }}</flux:text>
                            <flux:text class="font-medium">{{ ucfirst($selectedSubscription->gateway ?? __('commerce::commerce.status.none')) }}</flux:text>
                        </div>
                        <div>
                            <flux:text size="sm" class="text-zinc-500">{{ __('commerce::commerce.subscriptions.detail.billing_cycle') }}</flux:text>
                            <flux:text class="font-medium">{{ ucfirst($selectedSubscription->billing_cycle ?? 'monthly') }}</flux:text>
                        </div>
                        <div>
                            <flux:text size="sm" class="text-zinc-500">{{ __('commerce::commerce.subscriptions.detail.created') }}</flux:text>
                            <flux:text class="font-medium">{{ $selectedSubscription->created_at->format('d M Y H:i') }}</flux:text>
                        </div>
                    </div>
                </flux:card>

                {{-- Workspace and Package Cards --}}
                <div class="grid grid-cols-2 gap-4">
                    <flux:card>
                        <flux:text size="sm" class="text-zinc-500">{{ __('commerce::commerce.subscriptions.detail.workspace') }}</flux:text>
                        <flux:text class="font-medium">{{ $selectedSubscription->workspace?->name }}</flux:text>
                    </flux:card>
                    <flux:card>
                        <flux:text size="sm" class="text-zinc-500">{{ __('commerce::commerce.subscriptions.detail.package') }}</flux:text>
                        <flux:text class="font-medium">{{ $selectedSubscription->workspacePackage?->package?->name }}</flux:text>
                        <flux:text size="sm" class="font-mono text-zinc-500">{{ $selectedSubscription->workspacePackage?->package?->code }}</flux:text>
                    </flux:card>
                </div>

                {{-- Billing Period Card with Progress --}}
                <flux:card>
                    <flux:heading size="sm" class="mb-3">{{ __('commerce::commerce.subscriptions.detail.current_period') }}</flux:heading>

                    {{-- Progress Bar --}}
                    @if ($periodStart && $periodEnd)
                        <div class="mb-4 space-y-2">
                            <div class="flex justify-between text-sm">
                                <flux:text size="sm" class="text-zinc-500">{{ __('commerce::commerce.subscriptions.detail.billing_progress') }}</flux:text>
                                <flux:text size="sm" class="{{ $daysRemaining <= 3 ? 'text-amber-600 font-medium' : 'text-zinc-500' }}">
                                    {{ $daysRemaining }} {{ __('commerce::commerce.subscriptions.detail.days_remaining') }}
                                </flux:text>
                            </div>
                            <div class="h-2 bg-zinc-200 dark:bg-zinc-700 rounded-full overflow-hidden">
                                <div
                                    class="h-full rounded-full transition-all duration-300 {{ $daysRemaining <= 3 ? 'bg-amber-500' : 'bg-green-500' }}"
                                    style="width: {{ $periodProgress }}%"
                                ></div>
                            </div>
                        </div>
                    @endif

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <flux:text size="sm" class="text-zinc-500">{{ __('commerce::commerce.subscriptions.detail.start') }}</flux:text>
                            <flux:text class="font-medium">{{ $selectedSubscription->current_period_start?->format('d M Y') }}</flux:text>
                        </div>
                        <div>
                            <flux:text size="sm" class="text-zinc-500">{{ __('commerce::commerce.subscriptions.detail.end') }}</flux:text>
                            <flux:text class="font-medium">{{ $selectedSubscription->current_period_end?->format('d M Y') }}</flux:text>
                        </div>
                    </div>
                </flux:card>

                {{-- Gateway Info Card --}}
                @if ($selectedSubscription->gateway_subscription_id)
                    <flux:card>
                        <flux:heading size="sm" class="mb-3">{{ __('commerce::commerce.subscriptions.detail.gateway_details') }}</flux:heading>
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <flux:text size="sm" class="text-zinc-500">{{ __('commerce::commerce.subscriptions.detail.subscription_id') }}</flux:text>
                                <flux:text class="font-mono text-sm">{{ $selectedSubscription->gateway_subscription_id }}</flux:text>
                            </div>
                            @if ($selectedSubscription->gateway_customer_id)
                                <div class="flex justify-between">
                                    <flux:text size="sm" class="text-zinc-500">{{ __('commerce::commerce.subscriptions.detail.customer_id') }}</flux:text>
                                    <flux:text class="font-mono text-sm">{{ $selectedSubscription->gateway_customer_id }}</flux:text>
                                </div>
                            @endif
                            @if ($selectedSubscription->gateway_price_id)
                                <div class="flex justify-between">
                                    <flux:text size="sm" class="text-zinc-500">{{ __('commerce::commerce.subscriptions.detail.price_id') }}</flux:text>
                                    <flux:text class="font-mono text-sm">{{ $selectedSubscription->gateway_price_id }}</flux:text>
                                </div>
                            @endif
                        </div>
                    </flux:card>
                @endif

                {{-- Cancellation Alert --}}
                @if ($selectedSubscription->cancelled_at)
                    <flux:card class="border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-900/20">
                        <div class="flex items-start gap-3">
                            <core:icon name="exclamation-triangle" class="size-5 text-red-600 dark:text-red-400 flex-shrink-0 mt-0.5" />
                            <div class="flex-1">
                                <flux:heading size="sm" class="text-red-900 dark:text-red-100 mb-2">{{ __('commerce::commerce.subscriptions.detail.cancellation') }}</flux:heading>
                                <div class="space-y-1">
                                    <flux:text size="sm" class="text-red-800 dark:text-red-200">
                                        {{ __('commerce::commerce.subscriptions.detail.cancelled_at') }}: {{ $selectedSubscription->cancelled_at->format('d M Y H:i') }}
                                    </flux:text>
                                    @if ($selectedSubscription->cancellation_reason)
                                        <flux:text size="sm" class="text-red-800 dark:text-red-200">
                                            {{ __('commerce::commerce.subscriptions.detail.reason') }}: {{ $selectedSubscription->cancellation_reason }}
                                        </flux:text>
                                    @endif
                                    @if ($selectedSubscription->ended_at)
                                        <flux:text size="sm" class="text-red-800 dark:text-red-200">
                                            {{ __('commerce::commerce.subscriptions.detail.ended_at') }}: {{ $selectedSubscription->ended_at->format('d M Y H:i') }}
                                        </flux:text>
                                    @elseif ($selectedSubscription->cancel_at_period_end)
                                        <flux:text size="sm" class="text-red-800 dark:text-red-200 font-medium">
                                            {{ __('commerce::commerce.subscriptions.detail.will_end_at_period_end') }}
                                        </flux:text>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </flux:card>
                @endif

                {{-- Trial Info Alert --}}
                @if ($selectedSubscription->trial_ends_at)
                    <flux:card class="border-blue-200 bg-blue-50 dark:border-blue-800 dark:bg-blue-900/20">
                        <div class="flex items-start gap-3">
                            <core:icon name="clock" class="size-5 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5" />
                            <div>
                                <flux:heading size="sm" class="text-blue-900 dark:text-blue-100">{{ __('commerce::commerce.subscriptions.detail.trial') }}</flux:heading>
                                <flux:text size="sm" class="text-blue-800 dark:text-blue-200">
                                    {{ __('commerce::commerce.subscriptions.detail.trial_ends') }}: {{ $selectedSubscription->trial_ends_at->format('d M Y H:i') }}
                                    ({{ $selectedSubscription->trial_ends_at->diffForHumans() }})
                                </flux:text>
                            </div>
                        </div>
                    </flux:card>
                @endif
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <flux:button wire:click="openExtendPeriod({{ $selectedSubscription->id }})" variant="ghost" icon="clock">{{ __('commerce::commerce.subscriptions.extend.title') }}</flux:button>
                <flux:button wire:click="openStatusChange({{ $selectedSubscription->id }})" variant="ghost" icon="pencil">{{ __('commerce::commerce.subscriptions.update_status.title') }}</flux:button>
                <flux:button wire:click="closeDetailModal">{{ __('commerce::commerce.actions.close') }}</flux:button>
            </div>
        @endif
    </core:modal>

    {{-- Status Update Modal --}}
    <core:modal wire:model="showStatusModal" class="max-w-md">
        @if ($selectedSubscription)
            <core:heading size="lg">{{ __('commerce::commerce.subscriptions.update_status.title') }}</core:heading>

            <form wire:submit="updateStatus" class="mt-4 space-y-4">
                <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                    <div class="text-sm text-gray-500">{{ __('commerce::commerce.subscriptions.update_status.workspace') }}</div>
                    <div class="font-medium">{{ $selectedSubscription->workspace?->name }}</div>
                </div>

                <core:select wire:model="newStatus" label="{{ __('commerce::commerce.subscriptions.update_status.new_status') }}">
                    @foreach (array_keys($this->statuses) as $status)
                        <option value="{{ $status }}">{{ $this->statuses[$status] }}</option>
                    @endforeach
                </core:select>

                <core:textarea wire:model="statusNote" label="{{ __('commerce::commerce.subscriptions.update_status.note') }}" rows="2" placeholder="{{ __('commerce::commerce.subscriptions.update_status.note_placeholder') }}" />

                <div class="flex justify-end gap-2 pt-4">
                    <core:button variant="ghost" wire:click="closeStatusModal">{{ __('commerce::commerce.actions.cancel') }}</core:button>
                    <core:button type="submit" variant="primary">{{ __('commerce::commerce.subscriptions.update_status.title') }}</core:button>
                </div>
            </form>
        @endif
    </core:modal>

    {{-- Extend Period Modal --}}
    <core:modal wire:model="showExtendModal" class="max-w-md">
        @if ($selectedSubscription)
            <core:heading size="lg">{{ __('commerce::commerce.subscriptions.extend.title') }}</core:heading>

            <form wire:submit="extendPeriod" class="mt-4 space-y-4">
                <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                    <div class="text-sm text-gray-500">{{ __('commerce::commerce.subscriptions.extend.current_period_ends') }}</div>
                    <div class="font-medium">{{ $selectedSubscription->current_period_end?->format('d M Y H:i') }}</div>
                </div>

                <core:input wire:model="extendDays" label="{{ __('commerce::commerce.subscriptions.extend.extend_by_days') }}" type="number" min="1" max="365" />

                <div class="rounded-lg bg-blue-50 p-3 dark:bg-blue-900/20">
                    <div class="text-sm text-blue-800 dark:text-blue-200">
                        {{ __('commerce::commerce.subscriptions.extend.new_end_date') }}: {{ $selectedSubscription->current_period_end?->addDays($extendDays)->format('d M Y H:i') }}
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-4">
                    <core:button variant="ghost" wire:click="closeExtendModal">{{ __('commerce::commerce.actions.cancel') }}</core:button>
                    <core:button type="submit" variant="primary">{{ __('commerce::commerce.subscriptions.extend.action') }}</core:button>
                </div>
            </form>
        @endif
    </core:modal>
</admin:module>
