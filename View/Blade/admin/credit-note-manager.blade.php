<admin:module title="Credit Notes" subtitle="Manage credit notes and store credits">
    <x-slot:actions>
        <core:button wire:click="openCreate" icon="plus">Issue Credit Note</core:button>
    </x-slot:actions>

    <admin:flash />

    {{-- Summary Stats --}}
    <div class="mb-6 grid grid-cols-4 gap-4">
        <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-4">
            <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">Total Issued</flux:text>
            <flux:heading size="xl">GBP {{ number_format($this->summaryStats['total_issued'], 2) }}</flux:heading>
        </div>
        <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-4">
            <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">Total Used</flux:text>
            <flux:heading size="xl">GBP {{ number_format($this->summaryStats['total_used'], 2) }}</flux:heading>
        </div>
        <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-4">
            <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">Available Balance</flux:text>
            <flux:heading size="xl" class="text-green-600 dark:text-green-400">GBP {{ number_format($this->summaryStats['total_available'], 2) }}</flux:heading>
        </div>
        <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-4">
            <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">Active Credits</flux:text>
            <flux:heading size="xl">{{ $this->summaryStats['count_active'] }}</flux:heading>
        </div>
    </div>

    <admin:filter-bar cols="4">
        <admin:search model="search" placeholder="Search by reference or customer..." />
        <admin:filter model="statusFilter" :options="$this->statuses" placeholder="All statuses" />
        <admin:filter model="reasonFilter" :options="$this->reasons" placeholder="All reasons" />
        <admin:clear-filters :fields="['search', 'statusFilter', 'reasonFilter', 'dateRange']" />
    </admin:filter-bar>

    <admin:manager-table
        :columns="$this->tableColumns"
        :rows="$this->tableRows"
        :rowIds="$this->tableRowIds"
        :pagination="$this->creditNotes"
        :selectable="true"
        :selected="$selected"
        empty="No credit notes found."
        emptyIcon="banknotes"
    >
        <x-slot:bulkActions>
            <flux:button wire:click="exportSelected" size="sm" icon="arrow-down-tray">Export</flux:button>
        </x-slot:bulkActions>
    </admin:manager-table>

    {{-- Create Credit Note Modal --}}
    <core:modal wire:model="showCreateModal" class="max-w-lg">
        <core:heading size="lg">Issue Credit Note</core:heading>

        <form wire:submit="create" class="mt-4 space-y-4">
            <flux:select wire:model.live="workspaceId" label="Workspace" required>
                <flux:select.option value="">Select workspace...</flux:select.option>
                @foreach ($this->workspaces as $workspace)
                    <flux:select.option value="{{ $workspace->id }}">{{ $workspace->name }}</flux:select.option>
                @endforeach
            </flux:select>

            @if ($workspaceId)
                <flux:select wire:model="userId" label="User" required>
                    <flux:select.option value="">Select user...</flux:select.option>
                    @foreach ($this->users as $user)
                        <flux:select.option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="amount" label="Amount" type="number" step="0.01" min="0.01" required />
                <flux:select wire:model="currency" label="Currency">
                    <flux:select.option value="GBP">GBP</flux:select.option>
                    <flux:select.option value="USD">USD</flux:select.option>
                    <flux:select.option value="EUR">EUR</flux:select.option>
                </flux:select>
            </div>

            <flux:select wire:model="reason" label="Reason" required>
                <flux:select.option value="">Select reason...</flux:select.option>
                @foreach ($this->reasons as $key => $label)
                    <flux:select.option value="{{ $key }}">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:textarea wire:model="description" label="Description (optional)" rows="3" />

            <div class="flex justify-end gap-2 pt-4">
                <flux:button variant="ghost" wire:click="closeCreateModal">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Issue Credit Note</flux:button>
            </div>
        </form>
    </core:modal>

    {{-- Detail Modal --}}
    <core:modal wire:model="showDetailModal" class="max-w-2xl">
        @if ($selectedCreditNote)
            <core:heading size="lg">Credit Note: {{ $selectedCreditNote->reference_number }}</core:heading>

            <div class="mt-4 space-y-6">
                {{-- Status Badge --}}
                <div class="flex items-center gap-2">
                    @php
                        $statusColors = [
                            'draft' => 'gray',
                            'issued' => 'blue',
                            'partially_applied' => 'amber',
                            'applied' => 'green',
                            'void' => 'red',
                        ];
                    @endphp
                    <flux:badge color="{{ $statusColors[$selectedCreditNote->status] ?? 'gray' }}">
                        {{ ucfirst(str_replace('_', ' ', $selectedCreditNote->status)) }}
                    </flux:badge>
                    <flux:badge color="gray">{{ $selectedCreditNote->getReasonLabel() }}</flux:badge>
                </div>

                {{-- Amount Summary --}}
                <div class="grid grid-cols-3 gap-4 rounded-lg bg-zinc-50 dark:bg-zinc-800/50 p-4">
                    <div>
                        <flux:text size="sm" class="text-zinc-500">Total Amount</flux:text>
                        <flux:heading size="lg">{{ $selectedCreditNote->currency }} {{ number_format($selectedCreditNote->amount, 2) }}</flux:heading>
                    </div>
                    <div>
                        <flux:text size="sm" class="text-zinc-500">Amount Used</flux:text>
                        <flux:heading size="lg">{{ $selectedCreditNote->currency }} {{ number_format($selectedCreditNote->amount_used, 2) }}</flux:heading>
                    </div>
                    <div>
                        <flux:text size="sm" class="text-zinc-500">Remaining</flux:text>
                        <flux:heading size="lg" class="text-green-600 dark:text-green-400">{{ $selectedCreditNote->currency }} {{ number_format($selectedCreditNote->getRemainingAmount(), 2) }}</flux:heading>
                    </div>
                </div>

                {{-- Details Grid --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <flux:text size="sm" class="text-zinc-500">Workspace</flux:text>
                        <flux:text>{{ $selectedCreditNote->workspace?->name ?? 'N/A' }}</flux:text>
                    </div>
                    <div>
                        <flux:text size="sm" class="text-zinc-500">User</flux:text>
                        <flux:text>{{ $selectedCreditNote->user?->name ?? 'N/A' }}</flux:text>
                        <flux:text size="sm" class="text-zinc-500">{{ $selectedCreditNote->user?->email }}</flux:text>
                    </div>
                    <div>
                        <flux:text size="sm" class="text-zinc-500">Created</flux:text>
                        <flux:text>{{ $selectedCreditNote->created_at->format('d M Y H:i') }}</flux:text>
                    </div>
                    <div>
                        <flux:text size="sm" class="text-zinc-500">Issued</flux:text>
                        <flux:text>{{ $selectedCreditNote->issued_at?->format('d M Y H:i') ?? 'Not issued' }}</flux:text>
                        @if ($selectedCreditNote->issuedByUser)
                            <flux:text size="sm" class="text-zinc-500">by {{ $selectedCreditNote->issuedByUser->name }}</flux:text>
                        @endif
                    </div>
                </div>

                {{-- Source Information --}}
                @if ($selectedCreditNote->order || $selectedCreditNote->refund)
                    <flux:separator />
                    <div>
                        <flux:heading size="sm" class="mb-2">Source</flux:heading>
                        @if ($selectedCreditNote->order)
                            <flux:text>From Order: <span class="font-mono">{{ $selectedCreditNote->order->order_number }}</span></flux:text>
                        @endif
                        @if ($selectedCreditNote->refund)
                            <flux:text>From Refund: #{{ $selectedCreditNote->refund->id }}</flux:text>
                        @endif
                    </div>
                @endif

                {{-- Applied To --}}
                @if ($selectedCreditNote->appliedToOrder)
                    <flux:separator />
                    <div>
                        <flux:heading size="sm" class="mb-2">Applied To</flux:heading>
                        <flux:text>Order: <span class="font-mono">{{ $selectedCreditNote->appliedToOrder->order_number }}</span></flux:text>
                        @if ($selectedCreditNote->applied_at)
                            <flux:text size="sm" class="text-zinc-500">Applied on {{ $selectedCreditNote->applied_at->format('d M Y H:i') }}</flux:text>
                        @endif
                    </div>
                @endif

                {{-- Description --}}
                @if ($selectedCreditNote->description)
                    <flux:separator />
                    <div>
                        <flux:heading size="sm" class="mb-2">Description</flux:heading>
                        <flux:text>{{ $selectedCreditNote->description }}</flux:text>
                    </div>
                @endif

                {{-- Void Information --}}
                @if ($selectedCreditNote->isVoid())
                    <flux:separator />
                    <div class="rounded-lg bg-red-50 dark:bg-red-900/20 p-3">
                        <flux:text class="text-red-800 dark:text-red-200">
                            Voided on {{ $selectedCreditNote->voided_at->format('d M Y H:i') }}
                            @if ($selectedCreditNote->voidedByUser)
                                by {{ $selectedCreditNote->voidedByUser->name }}
                            @endif
                        </flux:text>
                    </div>
                @endif

                <div class="flex justify-end pt-4">
                    <flux:button variant="ghost" wire:click="closeDetailModal">Close</flux:button>
                </div>
            </div>
        @endif
    </core:modal>

    {{-- Void Confirmation Modal --}}
    <core:modal wire:model="showVoidModal" class="max-w-md">
        <core:heading size="lg">Void Credit Note</core:heading>

        @if ($creditNoteToVoid)
            <div class="mt-4 space-y-4">
                <flux:text>Are you sure you want to void credit note <strong>{{ $creditNoteToVoid->reference_number }}</strong>?</flux:text>
                <flux:text>Amount: <strong>{{ $creditNoteToVoid->currency }} {{ number_format($creditNoteToVoid->amount, 2) }}</strong></flux:text>

                <div class="rounded-lg bg-amber-50 dark:bg-amber-900/20 p-3">
                    <flux:text size="sm" class="text-amber-800 dark:text-amber-200">This action cannot be undone. The credit will no longer be available for use.</flux:text>
                </div>

                <div class="flex justify-end gap-2 pt-4">
                    <flux:button variant="ghost" wire:click="closeVoidModal">Cancel</flux:button>
                    <flux:button variant="danger" wire:click="voidCreditNote">Void Credit Note</flux:button>
                </div>
            </div>
        @endif
    </core:modal>
</admin:module>
