<admin:module title="{{ __('commerce::commerce.referrals.title') }}" subtitle="{{ __('commerce::commerce.referrals.subtitle') }}">
    {{-- Stats Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="rounded-lg bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 p-4">
            <div class="text-sm text-zinc-500 dark:text-zinc-400">Total Referrals</div>
            <div class="text-2xl font-bold text-zinc-900 dark:text-white">{{ number_format($this->stats['total_referrals']) }}</div>
        </div>
        <div class="rounded-lg bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 p-4">
            <div class="text-sm text-zinc-500 dark:text-zinc-400">Total Commissions</div>
            <div class="text-2xl font-bold text-zinc-900 dark:text-white">GBP {{ number_format($this->stats['total_commissions'], 2) }}</div>
        </div>
        <div class="rounded-lg bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 p-4">
            <div class="text-sm text-zinc-500 dark:text-zinc-400">Pending Payouts</div>
            <div class="text-2xl font-bold text-amber-600">GBP {{ number_format($this->stats['pending_payouts'], 2) }}</div>
        </div>
        <div class="rounded-lg bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 p-4">
            <div class="text-sm text-zinc-500 dark:text-zinc-400">Paid Out</div>
            <div class="text-2xl font-bold text-green-600">GBP {{ number_format($this->stats['completed_payouts'], 2) }}</div>
        </div>
    </div>

    {{-- Tabs --}}
    <div class="border-b border-zinc-200 dark:border-zinc-700 mb-6">
        <nav class="-mb-px flex gap-6">
            @foreach(['referrals' => 'Referrals', 'commissions' => 'Commissions', 'payouts' => 'Payouts', 'codes' => 'Codes'] as $tabKey => $tabLabel)
                <button
                    wire:click="switchTab('{{ $tabKey }}')"
                    class="py-2 px-1 border-b-2 font-medium text-sm transition-colors {{ $tab === $tabKey ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 hover:border-zinc-300 dark:text-zinc-400 dark:hover:text-zinc-300' }}"
                >
                    {{ $tabLabel }}
                </button>
            @endforeach
        </nav>
    </div>

    <x-slot:actions>
        @if($tab === 'commissions')
            <core:button wire:click="matureCommissions" icon="clock" variant="ghost">Mature Ready</core:button>
        @endif
        @if($tab === 'codes')
            <core:button wire:click="openCreateCode" icon="plus">New Code</core:button>
        @endif
    </x-slot:actions>

    <admin:flash />

    <admin:filter-bar cols="3">
        <admin:search model="search" placeholder="Search..." />
        <admin:filter model="statusFilter" :options="$this->statusOptions" placeholder="All statuses" />
        <admin:clear-filters :fields="['search', 'statusFilter']" />
    </admin:filter-bar>

    {{-- Referrals Tab --}}
    @if($tab === 'referrals')
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                <thead class="bg-zinc-50 dark:bg-zinc-800">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-zinc-500 uppercase">Referrer</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-zinc-500 uppercase">Referee</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-zinc-500 uppercase">Code</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-zinc-500 uppercase">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-zinc-500 uppercase">Signed Up</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-zinc-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse($this->referrals as $referral)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                            <td class="px-4 py-3">
                                <div class="font-medium text-zinc-900 dark:text-white">{{ $referral->referrer?->email ?? 'Unknown' }}</div>
                            </td>
                            <td class="px-4 py-3">
                                @if($referral->referee)
                                    <div class="font-medium text-zinc-900 dark:text-white">{{ $referral->referee->email }}</div>
                                @else
                                    <span class="text-zinc-400">-</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <code class="text-xs bg-zinc-100 dark:bg-zinc-700 px-2 py-1 rounded">{{ $referral->code }}</code>
                            </td>
                            <td class="px-4 py-3 text-center">
                                @php
                                    $statusColor = match($referral->status) {
                                        'pending' => 'gray',
                                        'converted' => 'blue',
                                        'qualified' => 'green',
                                        'disqualified' => 'red',
                                        default => 'gray',
                                    };
                                @endphp
                                <flux:badge color="{{ $statusColor }}">{{ ucfirst($referral->status) }}</flux:badge>
                            </td>
                            <td class="px-4 py-3 text-zinc-500 text-sm">
                                {{ $referral->signed_up_at?->format('d M Y') ?? '-' }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                <flux:button wire:click="viewReferral({{ $referral->id }})" size="sm" icon="eye" variant="ghost" />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-zinc-500">No referrals found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $this->referrals->links() }}</div>
    @endif

    {{-- Commissions Tab --}}
    @if($tab === 'commissions')
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                <thead class="bg-zinc-50 dark:bg-zinc-800">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-zinc-500 uppercase">Referrer</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-zinc-500 uppercase">Referee</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-zinc-500 uppercase">Order</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-zinc-500 uppercase">Commission</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-zinc-500 uppercase">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-zinc-500 uppercase">Matures</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse($this->commissions as $commission)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                            <td class="px-4 py-3">
                                <div class="font-medium text-zinc-900 dark:text-white">{{ $commission->referrer?->email ?? 'Unknown' }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="text-zinc-600 dark:text-zinc-400">{{ $commission->referral?->referee?->email ?? '-' }}</div>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="font-mono">{{ $commission->currency }} {{ number_format($commission->order_amount, 2) }}</div>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="font-mono font-medium text-green-600">{{ $commission->currency }} {{ number_format($commission->commission_amount, 2) }}</div>
                                <div class="text-xs text-zinc-400">{{ $commission->commission_rate }}%</div>
                            </td>
                            <td class="px-4 py-3 text-center">
                                @php
                                    $statusColor = match($commission->status) {
                                        'pending' => 'amber',
                                        'matured' => 'green',
                                        'paid' => 'blue',
                                        'cancelled' => 'red',
                                        default => 'gray',
                                    };
                                @endphp
                                <flux:badge color="{{ $statusColor }}">{{ ucfirst($commission->status) }}</flux:badge>
                            </td>
                            <td class="px-4 py-3 text-zinc-500 text-sm">
                                {{ $commission->matures_at?->format('d M Y') ?? '-' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-zinc-500">No commissions found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $this->commissions->links() }}</div>
    @endif

    {{-- Payouts Tab --}}
    @if($tab === 'payouts')
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                <thead class="bg-zinc-50 dark:bg-zinc-800">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-zinc-500 uppercase">Number</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-zinc-500 uppercase">User</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-zinc-500 uppercase">Method</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-zinc-500 uppercase">Amount</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-zinc-500 uppercase">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-zinc-500 uppercase">Requested</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-zinc-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse($this->payouts as $payout)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                            <td class="px-4 py-3">
                                <code class="text-xs">{{ $payout->payout_number }}</code>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-zinc-900 dark:text-white">{{ $payout->user?->email ?? 'Unknown' }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <flux:badge color="{{ $payout->method === 'btc' ? 'orange' : 'blue' }}">
                                    {{ $payout->method === 'btc' ? 'Bitcoin' : 'Credit' }}
                                </flux:badge>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="font-mono font-medium">{{ $payout->currency }} {{ number_format($payout->amount, 2) }}</div>
                                @if($payout->btc_amount)
                                    <div class="text-xs text-zinc-400">{{ $payout->btc_amount }} BTC</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                @php
                                    $statusColor = match($payout->status) {
                                        'requested' => 'amber',
                                        'processing' => 'blue',
                                        'completed' => 'green',
                                        'failed' => 'red',
                                        'cancelled' => 'gray',
                                        default => 'gray',
                                    };
                                @endphp
                                <flux:badge color="{{ $statusColor }}">{{ ucfirst($payout->status) }}</flux:badge>
                            </td>
                            <td class="px-4 py-3 text-zinc-500 text-sm">
                                {{ $payout->requested_at?->format('d M Y H:i') ?? '-' }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if($payout->isPending())
                                    <flux:button wire:click="openProcessPayout({{ $payout->id }})" size="sm" icon="banknotes" variant="ghost" />
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-zinc-500">No payouts found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $this->payouts->links() }}</div>
    @endif

    {{-- Codes Tab --}}
    @if($tab === 'codes')
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                <thead class="bg-zinc-50 dark:bg-zinc-800">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-zinc-500 uppercase">Code</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-zinc-500 uppercase">Type</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-zinc-500 uppercase">Owner</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-zinc-500 uppercase">Commission</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-zinc-500 uppercase">Uses</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-zinc-500 uppercase">Status</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-zinc-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse($this->codes as $code)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                            <td class="px-4 py-3">
                                <code class="font-mono text-sm">{{ $code->code }}</code>
                                @if($code->campaign_name)
                                    <div class="text-xs text-zinc-400">{{ $code->campaign_name }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <flux:badge color="{{ $code->type === 'campaign' ? 'purple' : 'gray' }}">{{ ucfirst($code->type) }}</flux:badge>
                            </td>
                            <td class="px-4 py-3">
                                {{ $code->user?->email ?? 'System' }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                {{ $code->commission_rate ? $code->commission_rate.'%' : 'Default' }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                {{ $code->uses_count }}{{ $code->max_uses ? '/'.$code->max_uses : '' }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                <flux:badge color="{{ $code->is_active ? 'green' : 'gray' }}">{{ $code->is_active ? 'Active' : 'Inactive' }}</flux:badge>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <div class="flex justify-center gap-1">
                                    <flux:button wire:click="openEditCode({{ $code->id }})" size="sm" icon="pencil" variant="ghost" />
                                    <flux:button wire:click="toggleCodeActive({{ $code->id }})" size="sm" icon="{{ $code->is_active ? 'pause' : 'play' }}" variant="ghost" />
                                    @if($code->uses_count === 0)
                                        <flux:button wire:click="deleteCode({{ $code->id }})" size="sm" icon="trash" variant="ghost" wire:confirm="Are you sure?" />
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-zinc-500">No referral codes found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $this->codes->links() }}</div>
    @endif

    {{-- Referral Detail Modal --}}
    <core:modal wire:model="showReferralModal" class="max-w-2xl">
        @if($this->viewingReferral)
            <core:heading size="lg">Referral Details</core:heading>

            <div class="mt-4 space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <div class="text-sm text-zinc-500">Referrer</div>
                        <div class="font-medium">{{ $this->viewingReferral->referrer?->email }}</div>
                    </div>
                    <div>
                        <div class="text-sm text-zinc-500">Referee</div>
                        <div class="font-medium">{{ $this->viewingReferral->referee?->email ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-sm text-zinc-500">Code</div>
                        <div class="font-mono">{{ $this->viewingReferral->code }}</div>
                    </div>
                    <div>
                        <div class="text-sm text-zinc-500">Status</div>
                        <div>{{ ucfirst($this->viewingReferral->status) }}</div>
                    </div>
                </div>

                <flux:separator />

                <div class="space-y-2">
                    <div class="text-sm text-zinc-500">Commissions</div>
                    @forelse($this->viewingReferral->commissions as $commission)
                        <div class="flex justify-between items-center p-2 bg-zinc-50 dark:bg-zinc-800 rounded">
                            <div>
                                <span class="font-mono">{{ $commission->currency }} {{ number_format($commission->commission_amount, 2) }}</span>
                                <flux:badge size="sm" color="{{ $commission->status === 'paid' ? 'green' : 'gray' }}">{{ ucfirst($commission->status) }}</flux:badge>
                            </div>
                            <div class="text-sm text-zinc-400">{{ $commission->created_at->format('d M Y') }}</div>
                        </div>
                    @empty
                        <div class="text-zinc-400 text-sm">No commissions yet.</div>
                    @endforelse
                </div>

                @if(!$this->viewingReferral->isDisqualified())
                    <div class="flex justify-end gap-2 pt-4">
                        <flux:button variant="ghost" wire:click="closeReferralModal">Close</flux:button>
                        <flux:button variant="danger" wire:click="disqualifyReferral({{ $this->viewingReferral->id }})" wire:confirm="Are you sure you want to disqualify this referral?">Disqualify</flux:button>
                    </div>
                @else
                    <div class="flex justify-end pt-4">
                        <flux:button variant="ghost" wire:click="closeReferralModal">Close</flux:button>
                    </div>
                @endif
            </div>
        @endif
    </core:modal>

    {{-- Payout Processing Modal --}}
    <core:modal wire:model="showPayoutModal" class="max-w-md">
        @if($this->processingPayout)
            <core:heading size="lg">Process Payout</core:heading>

            <div class="mt-4 space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <div class="text-sm text-zinc-500">User</div>
                        <div class="font-medium">{{ $this->processingPayout->user?->email }}</div>
                    </div>
                    <div>
                        <div class="text-sm text-zinc-500">Amount</div>
                        <div class="font-mono font-medium">{{ $this->processingPayout->currency }} {{ number_format($this->processingPayout->amount, 2) }}</div>
                    </div>
                    <div>
                        <div class="text-sm text-zinc-500">Method</div>
                        <div>{{ $this->processingPayout->method === 'btc' ? 'Bitcoin' : 'Account Credit' }}</div>
                    </div>
                    @if($this->processingPayout->btc_address)
                        <div>
                            <div class="text-sm text-zinc-500">BTC Address</div>
                            <div class="font-mono text-xs break-all">{{ $this->processingPayout->btc_address }}</div>
                        </div>
                    @endif
                </div>

                <flux:separator />

                @if($this->processingPayout->isRequested())
                    <flux:button wire:click="processPayout" class="w-full">Mark as Processing</flux:button>
                @endif

                @if($this->processingPayout->isProcessing())
                    @if($this->processingPayout->isBtcPayout())
                        <div class="space-y-3">
                            <flux:input wire:model="payoutBtcTxid" label="BTC Transaction ID" placeholder="Enter transaction ID" />
                            <div class="grid grid-cols-2 gap-3">
                                <flux:input wire:model="payoutBtcAmount" label="BTC Amount" type="number" step="0.00000001" />
                                <flux:input wire:model="payoutBtcRate" label="BTC Rate" type="number" step="0.01" />
                            </div>
                        </div>
                    @endif

                    <div class="flex gap-2">
                        <flux:button wire:click="completePayout" variant="primary" class="flex-1">Complete</flux:button>
                    </div>

                    <flux:separator />

                    <div class="space-y-3">
                        <flux:textarea wire:model="payoutFailReason" label="Failure Reason" rows="2" />
                        <flux:button wire:click="failPayout" variant="danger" class="w-full">Mark as Failed</flux:button>
                    </div>
                @endif

                <div class="flex justify-end pt-4">
                    <flux:button variant="ghost" wire:click="closePayoutModal">Close</flux:button>
                </div>
            </div>
        @endif
    </core:modal>

    {{-- Code Modal --}}
    <core:modal wire:model="showCodeModal" class="max-w-lg">
        <core:heading size="lg">{{ $editingCodeId ? 'Edit Referral Code' : 'Create Referral Code' }}</core:heading>

        <form wire:submit="saveCode" class="mt-4 space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="codeCode" label="Code" placeholder="SUMMER2026" class="font-mono uppercase" required />
                <flux:select wire:model="codeType" label="Type">
                    <flux:select.option value="custom">Custom</flux:select.option>
                    <flux:select.option value="campaign">Campaign</flux:select.option>
                    <flux:select.option value="user">User</flux:select.option>
                </flux:select>
            </div>

            <flux:input wire:model="codeCampaignName" label="Campaign Name" placeholder="Summer Promotion 2026" />

            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="codeCommissionRate" label="Commission Rate (%)" type="number" step="0.01" placeholder="Default (10%)" />
                <flux:input wire:model="codeCookieDays" label="Cookie Duration (days)" type="number" min="1" max="365" required />
            </div>

            <flux:input wire:model="codeMaxUses" label="Max Uses" type="number" min="1" placeholder="Unlimited" />

            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="codeValidFrom" label="Valid From" type="date" />
                <flux:input wire:model="codeValidUntil" label="Valid Until" type="date" />
            </div>

            <flux:checkbox wire:model="codeIsActive" label="Active" />

            <div class="flex justify-end gap-2 pt-4">
                <flux:button variant="ghost" wire:click="closeCodeModal" type="button">Cancel</flux:button>
                <flux:button type="submit" variant="primary">{{ $editingCodeId ? 'Update' : 'Create' }}</flux:button>
            </div>
        </form>
    </core:modal>
</admin:module>
