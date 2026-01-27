<?php

declare(strict_types=1);

namespace Core\Mod\Commerce\View\Modal\Admin;

use Core\Mod\Commerce\Models\Order;
use Core\Mod\Tenant\Models\Workspace;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Orders')]
class OrderManager extends Component
{
    use WithPagination;

    // Bulk selection
    public array $selected = [];

    public bool $selectAll = false;

    // Filters
    public string $search = '';

    public string $statusFilter = '';

    public string $typeFilter = '';

    public string $dateRange = '';

    public ?int $workspaceFilter = null;

    // Order detail modal
    public bool $showDetailModal = false;

    public ?Order $selectedOrder = null;

    // Status update modal
    public bool $showStatusModal = false;

    public string $newStatus = '';

    public string $statusNote = '';

    /**
     * Authorize access - Hades tier only.
     */
    public function mount(): void
    {
        if (! auth()->user()?->isHades()) {
            abort(403, 'Hades tier required for order management.');
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
        $this->selected = [];
        $this->selectAll = false;
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
        $this->selected = [];
        $this->selectAll = false;
    }

    public function updatingDateRange(): void
    {
        $this->resetPage();
        $this->selected = [];
        $this->selectAll = false;
    }

    public function updatedSelectAll(bool $value): void
    {
        if ($value) {
            $this->selected = $this->orders->pluck('id')->map(fn ($id) => (string) $id)->all();
        } else {
            $this->selected = [];
        }
    }

    public function exportSelected(): void
    {
        if (empty($this->selected)) {
            session()->flash('error', __('commerce::commerce.bulk.no_selection'));

            return;
        }

        $orders = Order::whereIn('id', $this->selected)->get();

        $csv = "Order Number,Customer,Email,Type,Status,Total,Currency,Created\n";
        foreach ($orders as $order) {
            $csv .= sprintf(
                "%s,%s,%s,%s,%s,%s,%s,%s\n",
                $order->order_number,
                str_replace(',', ' ', $order->billing_name ?: $order->user?->name),
                $order->billing_email ?: $order->user?->email,
                $order->type ?? 'unknown',
                $order->status,
                number_format($order->total, 2),
                $order->currency,
                $order->created_at->format('Y-m-d H:i:s')
            );
        }

        $this->dispatch('download-csv', filename: 'orders-export-'.now()->format('Y-m-d').'.csv', content: $csv);
        session()->flash('message', __('commerce::commerce.bulk.export_success', ['count' => count($this->selected)]));
        $this->selected = [];
        $this->selectAll = false;
    }

    public function bulkUpdateStatus(string $status): void
    {
        if (empty($this->selected)) {
            session()->flash('error', __('commerce::commerce.bulk.no_selection'));

            return;
        }

        $validStatuses = ['pending', 'processing', 'paid', 'failed', 'refunded', 'cancelled'];

        if (! in_array($status, $validStatuses)) {
            session()->flash('error', 'Invalid status selected.');

            return;
        }

        $count = Order::whereIn('id', $this->selected)->update([
            'status' => $status,
        ]);

        session()->flash('message', __('commerce::commerce.bulk.status_updated', ['count' => $count, 'status' => ucfirst($status)]));
        $this->selected = [];
        $this->selectAll = false;
    }

    public function viewOrder(int $id): void
    {
        $this->selectedOrder = Order::with([
            'workspace',
            'user',
            'items',
            'coupon',
            'invoice.payment',
        ])->findOrFail($id);

        $this->showDetailModal = true;
    }

    public function openStatusChange(int $id): void
    {
        $this->selectedOrder = Order::findOrFail($id);
        $this->newStatus = $this->selectedOrder->status;
        $this->statusNote = '';
        $this->showStatusModal = true;
    }

    public function updateStatus(): void
    {
        if (! $this->selectedOrder) {
            return;
        }

        $validStatuses = ['pending', 'processing', 'paid', 'failed', 'refunded', 'cancelled'];

        if (! in_array($this->newStatus, $validStatuses)) {
            session()->flash('error', 'Invalid status selected.');

            return;
        }

        $oldStatus = $this->selectedOrder->status;

        $this->selectedOrder->update([
            'status' => $this->newStatus,
            'metadata' => array_merge($this->selectedOrder->metadata ?? [], [
                'status_history' => array_merge(
                    $this->selectedOrder->metadata['status_history'] ?? [],
                    [[
                        'from' => $oldStatus,
                        'to' => $this->newStatus,
                        'note' => $this->statusNote ?: null,
                        'by' => auth()->id(),
                        'at' => now()->toIso8601String(),
                    ]]
                ),
            ]),
        ]);

        if ($this->newStatus === 'paid' && ! $this->selectedOrder->paid_at) {
            $this->selectedOrder->update(['paid_at' => now()]);
        }

        session()->flash('message', "Order status updated from {$oldStatus} to {$this->newStatus}.");
        $this->closeStatusModal();
    }

    public function closeDetailModal(): void
    {
        $this->showDetailModal = false;
        $this->selectedOrder = null;
    }

    public function closeStatusModal(): void
    {
        $this->showStatusModal = false;
        $this->selectedOrder = null;
        $this->newStatus = '';
        $this->statusNote = '';
    }

    #[Computed]
    public function orders()
    {
        return Order::query()
            ->with(['workspace', 'user'])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('order_number', 'like', "%{$this->search}%")
                        ->orWhere('billing_email', 'like', "%{$this->search}%")
                        ->orWhere('billing_name', 'like', "%{$this->search}%");
                });
            })
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->typeFilter, fn ($q) => $q->where('type', $this->typeFilter))
            ->when($this->dateRange, function ($query) {
                $startDate = match ($this->dateRange) {
                    'today' => now()->startOfDay(),
                    '7d' => now()->subDays(7)->startOfDay(),
                    '30d' => now()->subDays(30)->startOfDay(),
                    '90d' => now()->subDays(90)->startOfDay(),
                    'this_month' => now()->startOfMonth(),
                    'last_month' => now()->subMonth()->startOfMonth(),
                    default => null,
                };

                if ($startDate) {
                    $query->where('created_at', '>=', $startDate);
                }

                if ($this->dateRange === 'last_month') {
                    $query->where('created_at', '<', now()->startOfMonth());
                }
            })
            ->when($this->workspaceFilter, fn ($q) => $q->where('workspace_id', $this->workspaceFilter))
            ->latest()
            ->paginate(25);
    }

    #[Computed]
    public function workspaces()
    {
        return Workspace::orderBy('name')->get(['id', 'name']);
    }

    #[Computed]
    public function statuses(): array
    {
        return [
            'pending' => 'Pending',
            'processing' => 'Processing',
            'paid' => 'Paid',
            'failed' => 'Failed',
            'refunded' => 'Refunded',
            'cancelled' => 'Cancelled',
        ];
    }

    #[Computed]
    public function types(): array
    {
        return [
            'new_subscription' => 'New Subscription',
            'renewal' => 'Renewal',
            'upgrade' => 'Upgrade',
            'downgrade' => 'Downgrade',
            'addon' => 'Add-on',
            'one_time' => 'One-time',
        ];
    }

    #[Computed]
    public function dateRangeOptions(): array
    {
        return [
            'today' => __('commerce::commerce.orders.date_range.today'),
            '7d' => __('commerce::commerce.orders.date_range.7d'),
            '30d' => __('commerce::commerce.orders.date_range.30d'),
            '90d' => __('commerce::commerce.orders.date_range.90d'),
            'this_month' => __('commerce::commerce.orders.date_range.this_month'),
            'last_month' => __('commerce::commerce.orders.date_range.last_month'),
        ];
    }

    #[Computed]
    public function tableColumns(): array
    {
        return [
            'Order',
            'Customer',
            'Type',
            ['label' => 'Total', 'align' => 'right'],
            ['label' => 'Status', 'align' => 'center'],
            'Date',
            ['label' => 'Actions', 'align' => 'center'],
        ];
    }

    #[Computed]
    public function tableRowIds(): array
    {
        return $this->orders->pluck('id')->all();
    }

    #[Computed]
    public function tableRows(): array
    {
        $statusColors = [
            'pending' => 'amber',
            'processing' => 'blue',
            'paid' => 'green',
            'failed' => 'red',
            'refunded' => 'purple',
            'cancelled' => 'gray',
        ];

        return $this->orders->map(function ($o) use ($statusColors) {
            $totalLines = [['bold' => $o->currency.' '.number_format($o->total, 2)]];
            if ($o->discount_amount > 0) {
                $totalLines[] = ['muted' => '-'.number_format($o->discount_amount, 2).' discount'];
            }

            return [
                [
                    'lines' => [
                        ['mono' => $o->order_number],
                        ['muted' => $o->workspace?->name],
                    ],
                ],
                [
                    'lines' => [
                        ['bold' => $o->billing_name ?: $o->user?->name],
                        ['muted' => $o->billing_email ?: $o->user?->email],
                    ],
                ],
                ['badge' => str_replace('_', ' ', ucfirst($o->type ?? 'unknown')), 'color' => 'gray'],
                ['lines' => $totalLines],
                ['badge' => ucfirst($o->status), 'color' => $statusColors[$o->status] ?? 'gray'],
                [
                    'lines' => [
                        ['bold' => $o->created_at->format('d M Y')],
                        ['muted' => $o->created_at->format('H:i')],
                    ],
                ],
                [
                    'actions' => [
                        ['icon' => 'eye', 'click' => "viewOrder({$o->id})", 'title' => 'View details'],
                        ['icon' => 'pencil', 'click' => "openStatusChange({$o->id})", 'title' => 'Change status'],
                    ],
                ],
            ];
        })->all();
    }

    public function render()
    {
        return view('commerce::admin.order-manager')
            ->layout('hub::admin.layouts.app', ['title' => 'Orders']);
    }
}
