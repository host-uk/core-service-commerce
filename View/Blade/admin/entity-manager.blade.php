<div>
    {{-- Page header --}}
    <div class="sm:flex sm:justify-between sm:items-center mb-8">
        <div class="mb-4 sm:mb-0">
            <h1 class="text-2xl md:text-3xl text-gray-800 dark:text-gray-100 font-bold">Commerce Entities</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">Manage M1/M2/M3 entity hierarchy</p>
        </div>
        <div class="grid grid-flow-col sm:auto-cols-max justify-start sm:justify-end gap-2">
            <button
                    wire:click="openCreate"
                    class="btn bg-violet-500 hover:bg-violet-600 text-white"
            >
                <i class="fa-solid fa-plus mr-2"></i>
                New M1 Entity
            </button>
        </div>
    </div>

    {{-- Flash messages --}}
    @if (session()->has('message'))
        <div class="mb-4 p-4 bg-green-100 dark:bg-green-900/30 border border-green-200 dark:border-green-800 rounded-lg text-green-700 dark:text-green-400">
            {{ session('message') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div class="mb-4 p-4 bg-red-100 dark:bg-red-900/30 border border-red-200 dark:border-red-800 rounded-lg text-red-700 dark:text-red-400">
            {{ session('error') }}
        </div>
    @endif

    {{-- Stats cards --}}
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-violet-100 dark:bg-violet-900/30 flex items-center justify-center">
                    <i class="fa-solid fa-sitemap text-violet-600 dark:text-violet-400"></i>
                </div>
                <div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $stats['total'] }}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">Total Entities</div>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                    <i class="fa-solid fa-building text-blue-600 dark:text-blue-400"></i>
                </div>
                <div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $stats['m1_count'] }}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">M1 Masters</div>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-orange-100 dark:bg-orange-900/30 flex items-center justify-center">
                    <i class="fa-solid fa-store text-orange-600 dark:text-orange-400"></i>
                </div>
                <div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $stats['m2_count'] }}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">M2 Facades</div>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
                    <i class="fa-solid fa-truck text-green-600 dark:text-green-400"></i>
                </div>
                <div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $stats['m3_count'] }}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">M3 Dropshippers</div>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center">
                    <i class="fa-solid fa-check-circle text-emerald-600 dark:text-emerald-400"></i>
                </div>
                <div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $stats['active'] }}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">Active</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Entity hierarchy tree --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
        <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Entity Hierarchy</h2>
        </div>

        @if($entities->isEmpty())
            <div class="p-12 text-center text-gray-500 dark:text-gray-400">
                <i class="fa-solid fa-sitemap text-4xl mb-4"></i>
                <p class="mb-4">No entities yet</p>
                <button wire:click="openCreate" class="btn bg-violet-500 hover:bg-violet-600 text-white">
                    Create your first M1 entity
                </button>
            </div>
        @else
            <div class="divide-y divide-gray-100 dark:divide-gray-700">
                @foreach($entities as $entity)
                    {{-- M1 Entity --}}
                    @include('admin.livewire.commerce.partials.entity-row', ['entity' => $entity, 'level' => 0])

                    {{-- M2 Children --}}
                    @foreach($entity->children as $m2)
                        @include('admin.livewire.commerce.partials.entity-row', ['entity' => $m2, 'level' => 1])

                        {{-- M3 Children --}}
                        @foreach($m2->children as $m3)
                            @include('admin.livewire.commerce.partials.entity-row', ['entity' => $m3, 'level' => 2])
                        @endforeach
                    @endforeach
                @endforeach
            </div>
        @endif
    </div>

    {{-- Create/Edit Modal --}}
    @if($showModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-center justify-center min-h-screen p-4">
                {{-- Background overlay --}}
                <div class="fixed inset-0 bg-gray-500/75 dark:bg-gray-900/75 transition-opacity"
                     wire:click="closeModal"></div>

                {{-- Modal panel --}}
                <div class="inline-block bg-white dark:bg-gray-800 rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full sm:p-6">
                    <div class="mb-4">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                            {{ $editingId ? 'Edit Entity' : 'Create Entity' }}
                        </h3>
                    </div>

                    <form wire:submit="save" class="space-y-4">
                        {{-- Code --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Code</label>
                            <input type="text"
                                   wire:model="code"
                                   class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 uppercase"
                                   placeholder="ORGORG"
                                    {{ $editingId ? 'disabled' : '' }}>
                            @error('code') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
                        </div>

                        {{-- Name --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Name</label>
                            <input type="text"
                                   wire:model="name"
                                   class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                   placeholder="Original Organics Ltd">
                            @error('name') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
                        </div>

                        {{-- Type --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Type</label>
                            <select wire:model="type"
                                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                    {{ $parent_id ? 'disabled' : '' }}>
                                @foreach($types as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('type') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
                        </div>

                        {{-- Parent (hidden if M1) --}}
                        @if($parent_id)
                            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3">
                                <span class="text-sm text-gray-500 dark:text-gray-400">Parent:</span>
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ \Core\Commerce\Models\Entity::find($parent_id)?->name }}
                                </span>
                            </div>
                        @endif

                        {{-- Workspace --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Linked
                                Workspace (optional)</label>
                            <select wire:model="workspace_id"
                                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                                <option value="">None</option>
                                @foreach($workspaces as $workspace)
                                    <option value="{{ $workspace->id }}">{{ $workspace->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Domain --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Domain
                                (optional)</label>
                            <input type="text"
                                   wire:model="domain"
                                   class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                   placeholder="waterbutts.com">
                        </div>

                        {{-- Currency & Timezone --}}
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Currency</label>
                                <select wire:model="currency"
                                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                                    @foreach($currencies as $curr)
                                        <option value="{{ $curr }}">{{ $curr }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Timezone</label>
                                <select wire:model="timezone"
                                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                                    @foreach($timezones as $tz => $label)
                                        <option value="{{ $tz }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        {{-- Active --}}
                        <div class="flex items-center gap-2">
                            <input type="checkbox"
                                   wire:model="is_active"
                                   id="is_active"
                                   class="rounded border-gray-300 text-violet-600 focus:ring-violet-500">
                            <label for="is_active" class="text-sm text-gray-700 dark:text-gray-300">Active</label>
                        </div>

                        {{-- Actions --}}
                        <div class="flex justify-end gap-3 pt-4 border-t dark:border-gray-700">
                            <button type="button"
                                    wire:click="closeModal"
                                    class="btn border-gray-200 dark:border-gray-600 hover:border-gray-300 text-gray-600 dark:text-gray-300">
                                Cancel
                            </button>
                            <button type="submit"
                                    class="btn bg-violet-500 hover:bg-violet-600 text-white">
                                {{ $editingId ? 'Update' : 'Create' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    {{-- Delete Confirmation Modal --}}
    @if($showDeleteModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-center justify-center min-h-screen p-4">
                <div class="fixed inset-0 bg-gray-500/75 dark:bg-gray-900/75 transition-opacity"
                     wire:click="$set('showDeleteModal', false)"></div>

                <div class="inline-block bg-white dark:bg-gray-800 rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:max-w-md sm:w-full sm:p-6">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 dark:bg-red-900/30 sm:mx-0 sm:h-10 sm:w-10">
                            <i class="fa-solid fa-exclamation-triangle text-red-600 dark:text-red-400"></i>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Delete Entity</h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    Are you sure you want to delete this entity? This action cannot be undone.
                                    All associated permissions will also be deleted.
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse gap-3">
                        <button type="button"
                                wire:click="delete"
                                class="btn bg-red-600 hover:bg-red-700 text-white">
                            Delete
                        </button>
                        <button type="button"
                                wire:click="$set('showDeleteModal', false)"
                                class="btn border-gray-200 dark:border-gray-600 hover:border-gray-300 text-gray-600 dark:text-gray-300">
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
