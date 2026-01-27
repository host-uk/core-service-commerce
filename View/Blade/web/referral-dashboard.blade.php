<div class="max-w-5xl mx-auto py-8 px-4 sm:px-6">
    {{-- Header --}}
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">Affiliate Dashboard</h1>
        <p class="text-zinc-500 dark:text-zinc-400">Track your referrals and earnings</p>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        <div class="rounded-xl bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 p-5">
            <div class="text-sm text-zinc-500 dark:text-zinc-400 mb-1">Available Balance</div>
            <div class="text-2xl font-bold text-green-600">GBP {{ number_format($this->stats['available_balance'], 2) }}</div>
        </div>
        <div class="rounded-xl bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 p-5">
            <div class="text-sm text-zinc-500 dark:text-zinc-400 mb-1">Pending</div>
            <div class="text-2xl font-bold text-amber-600">GBP {{ number_format($this->stats['pending_balance'], 2) }}</div>
        </div>
        <div class="rounded-xl bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 p-5">
            <div class="text-sm text-zinc-500 dark:text-zinc-400 mb-1">Lifetime Earnings</div>
            <div class="text-2xl font-bold text-zinc-900 dark:text-white">GBP {{ number_format($this->stats['lifetime_earnings'], 2) }}</div>
        </div>
        <div class="rounded-xl bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 p-5">
            <div class="text-sm text-zinc-500 dark:text-zinc-400 mb-1">Total Referrals</div>
            <div class="text-2xl font-bold text-zinc-900 dark:text-white">{{ number_format($this->stats['total_referrals']) }}</div>
        </div>
    </div>

    {{-- Referral Link --}}
    <div class="rounded-xl bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 border border-blue-200 dark:border-blue-800 p-5 mb-8">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h3 class="font-semibold text-zinc-900 dark:text-white">Your Referral Link</h3>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">Share this link to earn 10% commission on all purchases</p>
            </div>
            <div class="flex items-center gap-2">
                <code class="px-3 py-2 bg-white dark:bg-zinc-800 rounded-lg text-sm font-mono text-zinc-700 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700 truncate max-w-xs">
                    {{ $this->referralLink }}
                </code>
                <flux:button
                    icon="clipboard"
                    variant="ghost"
                    x-on:click="navigator.clipboard.writeText('{{ $this->referralLink }}'); $dispatch('notify', {message: 'Copied to clipboard'})"
                />
            </div>
        </div>
    </div>

    {{-- Actions --}}
    <div class="flex justify-between items-center mb-6">
        {{-- Tabs --}}
        <div class="flex gap-4">
            @foreach(['overview' => 'Overview', 'referrals' => 'Referrals', 'commissions' => 'Commissions', 'payouts' => 'Payouts'] as $tabKey => $tabLabel)
                <button
                    wire:click="switchTab('{{ $tabKey }}')"
                    class="px-4 py-2 text-sm font-medium rounded-lg transition-colors {{ $tab === $tabKey ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800' }}"
                >
                    {{ $tabLabel }}
                </button>
            @endforeach
        </div>

        {{-- Payout Button --}}
        @if($this->stats['available_balance'] >= 10)
            <flux:button wire:click="openPayoutModal" icon="banknotes">Request Payout</flux:button>
        @endif
    </div>

    @if(session('message'))
        <div class="mb-6 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg text-green-700 dark:text-green-400">
            {{ session('message') }}
        </div>
    @endif

    @if(session('error'))
        <div class="mb-6 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg text-red-700 dark:text-red-400">
            {{ session('error') }}
        </div>
    @endif

    {{-- Overview Tab --}}
    @if($tab === 'overview')
        <div class="grid md:grid-cols-2 gap-6">
            {{-- Recent Referrals --}}
            <div class="rounded-xl bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 p-5">
                <h3 class="font-semibold text-zinc-900 dark:text-white mb-4">Recent Referrals</h3>
                @forelse($this->referrals->take(5) as $referral)
                    <div class="flex justify-between items-center py-2 border-b border-zinc-100 dark:border-zinc-700 last:border-0">
                        <div>
                            <div class="text-sm font-medium text-zinc-900 dark:text-white">{{ $referral->referee?->email ?? 'Pending signup' }}</div>
                            <div class="text-xs text-zinc-400">{{ $referral->created_at->diffForHumans() }}</div>
                        </div>
                        <flux:badge color="{{ $referral->status === 'qualified' ? 'green' : 'gray' }}">{{ ucfirst($referral->status) }}</flux:badge>
                    </div>
                @empty
                    <p class="text-zinc-400 text-sm">No referrals yet. Share your link to get started.</p>
                @endforelse
            </div>

            {{-- Recent Earnings --}}
            <div class="rounded-xl bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 p-5">
                <h3 class="font-semibold text-zinc-900 dark:text-white mb-4">Recent Earnings</h3>
                @forelse($this->commissions->take(5) as $commission)
                    <div class="flex justify-between items-center py-2 border-b border-zinc-100 dark:border-zinc-700 last:border-0">
                        <div>
                            <div class="text-sm font-medium text-zinc-900 dark:text-white">{{ $commission->referral?->referee?->email ?? 'Unknown' }}</div>
                            <div class="text-xs text-zinc-400">{{ $commission->created_at->diffForHumans() }}</div>
                        </div>
                        <div class="text-right">
                            <div class="font-mono text-sm font-medium text-green-600">+{{ $commission->currency }} {{ number_format($commission->commission_amount, 2) }}</div>
                            <flux:badge size="sm" color="{{ $commission->status === 'matured' ? 'green' : ($commission->status === 'pending' ? 'amber' : 'gray') }}">{{ ucfirst($commission->status) }}</flux:badge>
                        </div>
                    </div>
                @empty
                    <p class="text-zinc-400 text-sm">No earnings yet. Earnings appear when your referrals make purchases.</p>
                @endforelse
            </div>
        </div>

        {{-- How It Works --}}
        <div class="mt-8 rounded-xl bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700 p-6">
            <h3 class="font-semibold text-zinc-900 dark:text-white mb-4">How It Works</h3>
            <div class="grid md:grid-cols-3 gap-6">
                <div class="flex gap-3">
                    <div class="w-8 h-8 rounded-full bg-blue-100 dark:bg-blue-900/50 flex items-center justify-center text-blue-600 dark:text-blue-400 font-bold text-sm shrink-0">1</div>
                    <div>
                        <div class="font-medium text-zinc-900 dark:text-white">Share your link</div>
                        <div class="text-sm text-zinc-500 dark:text-zinc-400">Add your referral link to your bio, tweets, or anywhere else</div>
                    </div>
                </div>
                <div class="flex gap-3">
                    <div class="w-8 h-8 rounded-full bg-blue-100 dark:bg-blue-900/50 flex items-center justify-center text-blue-600 dark:text-blue-400 font-bold text-sm shrink-0">2</div>
                    <div>
                        <div class="font-medium text-zinc-900 dark:text-white">People sign up</div>
                        <div class="text-sm text-zinc-500 dark:text-zinc-400">When someone clicks and creates an account, they become your referral</div>
                    </div>
                </div>
                <div class="flex gap-3">
                    <div class="w-8 h-8 rounded-full bg-blue-100 dark:bg-blue-900/50 flex items-center justify-center text-blue-600 dark:text-blue-400 font-bold text-sm shrink-0">3</div>
                    <div>
                        <div class="font-medium text-zinc-900 dark:text-white">Earn 10% forever</div>
                        <div class="text-sm text-zinc-500 dark:text-zinc-400">You earn 10% of every payment they ever make, for life</div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Referrals Tab --}}
    @if($tab === 'referrals')
        <div class="rounded-xl bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 overflow-hidden">
            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                <thead class="bg-zinc-50 dark:bg-zinc-800/50">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-medium text-zinc-500 uppercase">Referee</th>
                        <th class="px-5 py-3 text-center text-xs font-medium text-zinc-500 uppercase">Status</th>
                        <th class="px-5 py-3 text-left text-xs font-medium text-zinc-500 uppercase">Signed Up</th>
                        <th class="px-5 py-3 text-right text-xs font-medium text-zinc-500 uppercase">Earnings</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse($this->referrals as $referral)
                        <tr>
                            <td class="px-5 py-4">
                                @if($referral->referee)
                                    <div class="font-medium text-zinc-900 dark:text-white">{{ $referral->referee->name ?? $referral->referee->email }}</div>
                                @else
                                    <span class="text-zinc-400">Pending signup</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-center">
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
                            <td class="px-5 py-4 text-sm text-zinc-500">
                                {{ $referral->signed_up_at?->format('d M Y') ?? '-' }}
                            </td>
                            <td class="px-5 py-4 text-right">
                                <span class="font-mono text-green-600">GBP {{ number_format($referral->total_commission, 2) }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-5 py-8 text-center text-zinc-400">No referrals yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $this->referrals->links() }}</div>
    @endif

    {{-- Commissions Tab --}}
    @if($tab === 'commissions')
        <div class="rounded-xl bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 overflow-hidden">
            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                <thead class="bg-zinc-50 dark:bg-zinc-800/50">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-medium text-zinc-500 uppercase">Referee</th>
                        <th class="px-5 py-3 text-right text-xs font-medium text-zinc-500 uppercase">Order</th>
                        <th class="px-5 py-3 text-right text-xs font-medium text-zinc-500 uppercase">Commission</th>
                        <th class="px-5 py-3 text-center text-xs font-medium text-zinc-500 uppercase">Status</th>
                        <th class="px-5 py-3 text-left text-xs font-medium text-zinc-500 uppercase">Matures</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse($this->commissions as $commission)
                        <tr>
                            <td class="px-5 py-4">
                                <div class="text-zinc-900 dark:text-white">{{ $commission->referral?->referee?->name ?? $commission->referral?->referee?->email ?? 'Unknown' }}</div>
                            </td>
                            <td class="px-5 py-4 text-right">
                                <span class="font-mono text-sm">{{ $commission->currency }} {{ number_format($commission->order_amount, 2) }}</span>
                            </td>
                            <td class="px-5 py-4 text-right">
                                <span class="font-mono font-medium text-green-600">{{ $commission->currency }} {{ number_format($commission->commission_amount, 2) }}</span>
                                <div class="text-xs text-zinc-400">{{ $commission->commission_rate }}%</div>
                            </td>
                            <td class="px-5 py-4 text-center">
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
                            <td class="px-5 py-4 text-sm text-zinc-500">
                                {{ $commission->matures_at?->format('d M Y') ?? '-' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-8 text-center text-zinc-400">No commissions yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $this->commissions->links() }}</div>
    @endif

    {{-- Payouts Tab --}}
    @if($tab === 'payouts')
        <div class="rounded-xl bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 overflow-hidden">
            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                <thead class="bg-zinc-50 dark:bg-zinc-800/50">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-medium text-zinc-500 uppercase">Number</th>
                        <th class="px-5 py-3 text-left text-xs font-medium text-zinc-500 uppercase">Method</th>
                        <th class="px-5 py-3 text-right text-xs font-medium text-zinc-500 uppercase">Amount</th>
                        <th class="px-5 py-3 text-center text-xs font-medium text-zinc-500 uppercase">Status</th>
                        <th class="px-5 py-3 text-left text-xs font-medium text-zinc-500 uppercase">Requested</th>
                        <th class="px-5 py-3 text-center text-xs font-medium text-zinc-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse($this->payouts as $payout)
                        <tr>
                            <td class="px-5 py-4">
                                <code class="text-xs">{{ $payout->payout_number }}</code>
                            </td>
                            <td class="px-5 py-4">
                                <flux:badge color="{{ $payout->method === 'btc' ? 'orange' : 'blue' }}">
                                    {{ $payout->method === 'btc' ? 'Bitcoin' : 'Credit' }}
                                </flux:badge>
                            </td>
                            <td class="px-5 py-4 text-right">
                                <span class="font-mono font-medium">{{ $payout->currency }} {{ number_format($payout->amount, 2) }}</span>
                                @if($payout->btc_txid)
                                    <div class="text-xs text-zinc-400">{{ Str::limit($payout->btc_txid, 16) }}</div>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-center">
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
                            <td class="px-5 py-4 text-sm text-zinc-500">
                                {{ $payout->requested_at?->format('d M Y') ?? '-' }}
                            </td>
                            <td class="px-5 py-4 text-center">
                                @if($payout->isRequested())
                                    <flux:button
                                        wire:click="cancelPayout({{ $payout->id }})"
                                        wire:confirm="Are you sure you want to cancel this payout request?"
                                        size="sm"
                                        variant="ghost"
                                        icon="x-mark"
                                    />
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-8 text-center text-zinc-400">No payouts yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $this->payouts->links() }}</div>
    @endif

    {{-- Payout Request Modal --}}
    <core:modal wire:model="showPayoutModal" class="max-w-md">
        <core:heading size="lg">Request Payout</core:heading>

        <form wire:submit="requestPayout" class="mt-4 space-y-4">
            <div>
                <div class="text-sm text-zinc-500 mb-2">Available Balance</div>
                <div class="text-2xl font-bold text-green-600">GBP {{ number_format($this->stats['available_balance'], 2) }}</div>
            </div>

            <flux:select wire:model.live="payoutMethod" label="Payout Method">
                <flux:select.option value="btc">Bitcoin (minimum GBP 10)</flux:select.option>
                <flux:select.option value="account_credit">Account Credit (no minimum)</flux:select.option>
            </flux:select>

            @if($payoutMethod === 'btc')
                <flux:input wire:model="payoutBtcAddress" label="Bitcoin Address" placeholder="Enter your BTC address" required />
            @endif

            <flux:input wire:model="payoutAmount" label="Amount (leave empty for full balance)" type="number" step="0.01" placeholder="{{ number_format($this->stats['available_balance'], 2) }}" />

            <div class="bg-zinc-50 dark:bg-zinc-800 rounded-lg p-3 text-sm text-zinc-600 dark:text-zinc-400">
                @if($payoutMethod === 'btc')
                    Bitcoin payouts are processed weekly. You will receive the BTC equivalent at the time of processing.
                @else
                    Account credit is applied immediately and can be used for any purchase.
                @endif
            </div>

            <div class="flex justify-end gap-2 pt-4">
                <flux:button variant="ghost" wire:click="closePayoutModal" type="button">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Request Payout</flux:button>
            </div>
        </form>
    </core:modal>
</div>
