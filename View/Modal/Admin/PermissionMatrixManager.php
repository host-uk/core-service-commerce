<?php

declare(strict_types=1);

namespace Core\Commerce\View\Modal\Admin;

use Core\Commerce\Models\Entity;
use Core\Commerce\Models\PermissionMatrix;
use Core\Commerce\Models\PermissionRequest;
use Core\Commerce\Services\PermissionLockedException;
use Core\Commerce\Services\PermissionMatrixService;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Manage Permission Matrix training and permissions.
 */
#[Layout('hub::admin.layouts.app')]
class PermissionMatrixManager extends Component
{
    use WithPagination;

    // Filters
    public ?int $entityFilter = null;

    public string $statusFilter = '';

    public string $search = '';

    // Training modal
    public bool $showTrainModal = false;

    public ?int $trainingEntityId = null;

    public string $trainingKey = '';

    public string $trainingScope = '';

    public bool $trainingAllow = true;

    public bool $trainingLock = false;

    // Bulk training
    public array $selectedRequests = [];

    protected PermissionMatrixService $matrix;

    public function boot(PermissionMatrixService $matrix): void
    {
        $this->matrix = $matrix;
    }

    /**
     * Authorize access - Hades tier only.
     */
    public function mount(): void
    {
        if (! auth()->user()?->isHades()) {
            abort(403, 'Hades tier required for permission matrix management.');
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingEntityFilter(): void
    {
        $this->resetPage();
    }

    public function openTrain(?int $requestId = null): void
    {
        if ($requestId) {
            $request = PermissionRequest::find($requestId);
            if ($request) {
                $this->trainingEntityId = $request->entity_id;
                $this->trainingKey = $request->action;
                $this->trainingScope = $request->scope ?? '';
            }
        }

        $this->trainingAllow = true;
        $this->trainingLock = false;
        $this->showTrainModal = true;
    }

    public function openTrainNew(): void
    {
        $this->trainingEntityId = null;
        $this->trainingKey = '';
        $this->trainingScope = '';
        $this->trainingAllow = true;
        $this->trainingLock = false;
        $this->showTrainModal = true;
    }

    public function train(): void
    {
        $this->validate([
            'trainingEntityId' => 'required|exists:commerce_entities,id',
            'trainingKey' => 'required|string|max:255',
            'trainingScope' => 'nullable|string|max:255',
        ]);

        $entity = Entity::findOrFail($this->trainingEntityId);

        try {
            if ($this->trainingLock) {
                $this->matrix->lock(
                    entity: $entity,
                    key: $this->trainingKey,
                    allowed: $this->trainingAllow,
                    scope: $this->trainingScope ?: null
                );
            } else {
                $this->matrix->train(
                    entity: $entity,
                    key: $this->trainingKey,
                    scope: $this->trainingScope ?: null,
                    allow: $this->trainingAllow
                );
            }

            // Mark related requests as trained
            $this->matrix->markRequestsTrained(
                $entity,
                $this->trainingKey,
                $this->trainingScope ?: null
            );

            $action = $this->trainingAllow ? 'allowed' : 'denied';
            $lock = $this->trainingLock ? ' (locked)' : '';
            session()->flash('message', "Permission '{$this->trainingKey}' {$action} for {$entity->name}{$lock}.");

            $this->closeTrainModal();

        } catch (PermissionLockedException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function bulkTrain(bool $allow): void
    {
        if (empty($this->selectedRequests)) {
            session()->flash('error', 'No requests selected.');

            return;
        }

        $trained = 0;
        $errors = [];

        foreach ($this->selectedRequests as $requestId) {
            $request = PermissionRequest::find($requestId);
            if (! $request) {
                continue;
            }

            try {
                $entity = $request->entity;
                if (! $entity) {
                    continue;
                }

                $this->matrix->train(
                    entity: $entity,
                    key: $request->action,
                    scope: $request->scope,
                    allow: $allow
                );

                $this->matrix->markRequestsTrained(
                    $entity,
                    $request->action,
                    $request->scope
                );

                $trained++;

            } catch (PermissionLockedException $e) {
                $errors[] = $e->getMessage();
            }
        }

        $this->selectedRequests = [];

        if ($errors) {
            session()->flash('error', implode(', ', $errors));
        }

        $action = $allow ? 'allowed' : 'denied';
        session()->flash('message', "{$trained} permissions {$action}.");
    }

    public function deletePermission(int $id): void
    {
        $permission = PermissionMatrix::findOrFail($id);

        if ($permission->locked) {
            session()->flash('error', 'Cannot delete a locked permission.');

            return;
        }

        $permission->delete();
        session()->flash('message', 'Permission deleted.');
    }

    public function unlockPermission(int $id): void
    {
        $permission = PermissionMatrix::findOrFail($id);

        try {
            $this->matrix->unlock($permission->entity, $permission->key, $permission->scope);
            session()->flash('message', 'Permission unlocked.');
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function closeTrainModal(): void
    {
        $this->showTrainModal = false;
        $this->trainingEntityId = null;
        $this->trainingKey = '';
        $this->trainingScope = '';
        $this->trainingAllow = true;
        $this->trainingLock = false;
    }

    public function render()
    {
        // Get pending requests
        $pendingRequests = PermissionRequest::query()
            ->with('entity')
            ->where('status', PermissionRequest::STATUS_PENDING)
            ->when($this->entityFilter, fn ($q) => $q->where('entity_id', $this->entityFilter))
            ->when($this->search, fn ($q) => $q->where('action', 'like', "%{$this->search}%"))
            ->latest()
            ->paginate(20, ['*'], 'pending_page');

        // Get trained permissions
        $permissions = PermissionMatrix::query()
            ->with('entity', 'setByEntity')
            ->when($this->entityFilter, fn ($q) => $q->where('entity_id', $this->entityFilter))
            ->when($this->search, fn ($q) => $q->where('key', 'like', "%{$this->search}%"))
            ->when($this->statusFilter === 'allowed', fn ($q) => $q->where('allowed', true))
            ->when($this->statusFilter === 'denied', fn ($q) => $q->where('allowed', false))
            ->when($this->statusFilter === 'locked', fn ($q) => $q->where('locked', true))
            ->orderBy('entity_id')
            ->orderBy('key')
            ->paginate(30, ['*'], 'permissions_page');

        return view('commerce::admin.permission-matrix-manager', [
            'pendingRequests' => $pendingRequests,
            'permissions' => $permissions,
            'entities' => Entity::active()->orderBy('path')->get(),
            'stats' => [
                'total_permissions' => PermissionMatrix::count(),
                'allowed' => PermissionMatrix::where('allowed', true)->count(),
                'denied' => PermissionMatrix::where('allowed', false)->count(),
                'locked' => PermissionMatrix::where('locked', true)->count(),
                'pending_requests' => PermissionRequest::where('status', PermissionRequest::STATUS_PENDING)->count(),
            ],
        ])->layout('hub::admin.layouts.app', ['title' => 'Permission Matrix']);
    }
}
