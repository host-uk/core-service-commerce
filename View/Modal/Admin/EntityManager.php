<?php

declare(strict_types=1);

namespace Core\Commerce\View\Modal\Admin;

use Core\Commerce\Models\Entity;
use Core\Mod\Tenant\Models\Workspace;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Manage Commerce Matrix entities (M1/M2/M3 hierarchy).
 */
#[Layout('hub::admin.layouts.app')]
class EntityManager extends Component
{
    // Modal states
    public bool $showModal = false;

    public bool $showDeleteModal = false;

    public ?int $editingId = null;

    public ?int $deletingId = null;

    // Form fields
    public string $code = '';

    public string $name = '';

    public string $type = Entity::TYPE_M1_MASTER;

    public ?int $parent_id = null;

    public ?int $workspace_id = null;

    public string $domain = '';

    public string $currency = 'GBP';

    public string $timezone = 'Europe/London';

    public bool $is_active = true;

    // View state
    public ?int $expandedEntity = null;

    /**
     * Authorize access - Hades tier only.
     */
    public function mount(): void
    {
        if (! auth()->user()?->isHades()) {
            abort(403, 'Hades tier required for entity management.');
        }
    }

    protected function rules(): array
    {
        $uniqueRule = $this->editingId
            ? 'unique:commerce_entities,code,'.$this->editingId
            : 'unique:commerce_entities,code';

        return [
            'code' => ['required', 'string', 'max:32', 'alpha_num', $uniqueRule],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:m1,m2,m3'],
            'parent_id' => ['nullable', 'exists:commerce_entities,id'],
            'workspace_id' => ['nullable', 'exists:workspaces,id'],
            'domain' => ['nullable', 'string', 'max:255'],
            'currency' => ['required', 'string', 'size:3'],
            'timezone' => ['required', 'string', 'max:50'],
            'is_active' => ['boolean'],
        ];
    }

    protected array $messages = [
        'code.alpha_num' => 'Code must be letters and numbers only.',
        'code.unique' => 'This code is already in use.',
    ];

    public function openCreate(?int $parentId = null): void
    {
        $this->resetForm();

        if ($parentId) {
            $parent = Entity::find($parentId);
            if ($parent) {
                $this->parent_id = $parentId;
                // Determine child type based on parent
                $this->type = match ($parent->type) {
                    Entity::TYPE_M1_MASTER => Entity::TYPE_M2_FACADE,
                    Entity::TYPE_M2_FACADE => Entity::TYPE_M3_DROPSHIP,
                    default => Entity::TYPE_M3_DROPSHIP,
                };
                // Inherit currency/timezone from parent
                $this->currency = $parent->currency;
                $this->timezone = $parent->timezone;
            }
        }

        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $entity = Entity::findOrFail($id);

        $this->editingId = $id;
        $this->code = $entity->code;
        $this->name = $entity->name;
        $this->type = $entity->type;
        $this->parent_id = $entity->parent_id;
        $this->workspace_id = $entity->workspace_id;
        $this->domain = $entity->domain ?? '';
        $this->currency = $entity->currency;
        $this->timezone = $entity->timezone;
        $this->is_active = $entity->is_active;

        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate();

        // Uppercase code
        $code = strtoupper($this->code);

        if ($this->editingId) {
            $entity = Entity::findOrFail($this->editingId);

            // Prevent changing type if has children
            if ($entity->type !== $this->type && $entity->children()->exists()) {
                session()->flash('error', 'Cannot change type of entity with children.');

                return;
            }

            // Prevent making root entity a child
            if (! $entity->parent_id && $this->parent_id) {
                session()->flash('error', 'Cannot move root entity under another entity.');

                return;
            }

            // Update path if code changed
            $oldCode = $entity->code;
            $newPath = $entity->parent
                ? $entity->parent->path.'/'.$code
                : $code;

            $entity->update([
                'code' => $code,
                'name' => $this->name,
                'type' => $this->type,
                'workspace_id' => $this->workspace_id,
                'domain' => $this->domain ?: null,
                'currency' => $this->currency,
                'timezone' => $this->timezone,
                'is_active' => $this->is_active,
                'path' => $newPath,
            ]);

            // Update descendant paths if code changed
            if ($oldCode !== $code) {
                $this->updateDescendantPaths($entity, $oldCode, $code);
            }

            session()->flash('message', 'Entity updated successfully.');
        } else {
            // Create new entity
            $parent = $this->parent_id ? Entity::find($this->parent_id) : null;

            if ($parent) {
                $entity = $parent->createChild($code, $this->name, $this->type, [
                    'workspace_id' => $this->workspace_id,
                    'domain' => $this->domain ?: null,
                    'currency' => $this->currency,
                    'timezone' => $this->timezone,
                    'is_active' => $this->is_active,
                ]);
            } else {
                $entity = Entity::createMaster($code, $this->name, [
                    'workspace_id' => $this->workspace_id,
                    'domain' => $this->domain ?: null,
                    'currency' => $this->currency,
                    'timezone' => $this->timezone,
                    'is_active' => $this->is_active,
                ]);
            }

            session()->flash('message', 'Entity created successfully.');
        }

        $this->closeModal();
    }

    protected function updateDescendantPaths(Entity $entity, string $oldCode, string $newCode): void
    {
        $oldPathPrefix = str_replace($newCode, $oldCode, $entity->path);

        foreach ($entity->getDescendants() as $descendant) {
            $descendant->update([
                'path' => str_replace($oldPathPrefix, $entity->path, $descendant->path),
            ]);
        }
    }

    public function confirmDelete(int $id): void
    {
        $this->deletingId = $id;
        $this->showDeleteModal = true;
    }

    public function delete(): void
    {
        if (! $this->deletingId) {
            return;
        }

        $entity = Entity::findOrFail($this->deletingId);

        // Check for children
        if ($entity->children()->exists()) {
            session()->flash('error', 'Cannot delete entity with children. Remove children first.');
            $this->showDeleteModal = false;

            return;
        }

        // Check for permissions
        if ($entity->permissions()->exists()) {
            $entity->permissions()->delete();
        }

        $entity->delete();
        session()->flash('message', "Entity '{$entity->name}' deleted successfully.");

        $this->showDeleteModal = false;
        $this->deletingId = null;
    }

    public function toggleExpand(int $id): void
    {
        $this->expandedEntity = $this->expandedEntity === $id ? null : $id;
    }

    public function toggleActive(int $id): void
    {
        $entity = Entity::findOrFail($id);
        $entity->update(['is_active' => ! $entity->is_active]);

        $status = $entity->is_active ? 'activated' : 'deactivated';
        session()->flash('message', "Entity '{$entity->name}' {$status}.");
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    protected function resetForm(): void
    {
        $this->editingId = null;
        $this->code = '';
        $this->name = '';
        $this->type = Entity::TYPE_M1_MASTER;
        $this->parent_id = null;
        $this->workspace_id = null;
        $this->domain = '';
        $this->currency = 'GBP';
        $this->timezone = 'Europe/London';
        $this->is_active = true;
    }

    public function render()
    {
        // Get root entities (M1) with nested children
        $entities = Entity::whereNull('parent_id')
            ->with(['children' => function ($query) {
                $query->with(['children' => function ($q) {
                    $q->orderBy('name');
                }])->orderBy('name');
            }])
            ->orderBy('name')
            ->get();

        return view('commerce::admin.entity-manager', [
            'entities' => $entities,
            'workspaces' => Workspace::orderBy('name')->get(['id', 'name']),
            'types' => [
                Entity::TYPE_M1_MASTER => 'M1 - Master Company',
                Entity::TYPE_M2_FACADE => 'M2 - Facade/Storefront',
                Entity::TYPE_M3_DROPSHIP => 'M3 - Dropshipper',
            ],
            'currencies' => ['GBP', 'USD', 'EUR'],
            'timezones' => [
                'Europe/London' => 'London (GMT/BST)',
                'Europe/Paris' => 'Paris (CET/CEST)',
                'America/New_York' => 'New York (EST/EDT)',
                'America/Los_Angeles' => 'Los Angeles (PST/PDT)',
                'UTC' => 'UTC',
            ],
            'stats' => [
                'total' => Entity::count(),
                'm1_count' => Entity::where('type', Entity::TYPE_M1_MASTER)->count(),
                'm2_count' => Entity::where('type', Entity::TYPE_M2_FACADE)->count(),
                'm3_count' => Entity::where('type', Entity::TYPE_M3_DROPSHIP)->count(),
                'active' => Entity::where('is_active', true)->count(),
            ],
        ])->layout('hub::admin.layouts.app', ['title' => 'Commerce Entities']);
    }
}
