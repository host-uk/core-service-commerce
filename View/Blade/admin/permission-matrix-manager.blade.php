<div>
    {{-- Page header --}}
    <div class="sm:flex sm:justify-between sm:items-center mb-8">
        <div class="mb-4 sm:mb-0">
            <h1 class="text-2xl md:text-3xl text-gray-800 dark:text-gray-100 font-bold">Permission Matrix</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">Train and manage entity permissions</p>
        </div>
        <div class="grid grid-flow-col sm:auto-cols-max justify-start sm:justify-end gap-2">
            <button
                wire:click="openTrainNew"
                class="btn bg-violet-500 hover:bg-violet-600 text-white"
            >
                <i class="fa-solid fa-plus mr-2"></i>
                Add Permission
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
                    <i class="fa-solid fa-shield text-violet-600 dark:text-violet-400"></i>
                </div>
                <div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $stats['total_permissions'] }}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">Total Permissions</div>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
                    <i class="fa-solid fa-check text-green-600 dark:text-green-400"></i>
                </div>
                <div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $stats['allowed'] }}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">Allowed</div>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center">
                    <i class="fa-solid fa-ban text-red-600 dark:text-red-400"></i>
                </div>
                <div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $stats['denied'] }}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">Denied</div>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center">
                    <i class="fa-solid fa-lock text-amber-600 dark:text-amber-400"></i>
                </div>
                <div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $stats['locked'] }}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">Locked</div>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-orange-100 dark:bg-orange-900/30 flex items-center justify-center">
                    <i class="fa-solid fa-clock text-orange-600 dark:text-orange-400"></i>
                </div>
                <div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $stats['pending_requests'] }}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">Pending</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Search</label>
                <input type="text"
                       wire:model.live.debounce.300ms="search"
                       class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                       placeholder="Search permissions...">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Entity</label>
                <select wire:model.live="entityFilter"
                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                    <option value="">All Entities</option>
                    @foreach($entities as $entity)
                        <option value="{{ $entity->id }}">{{ $entity->path }} - {{ $entity->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
                <select wire:model.live="statusFilter"
                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                    <option value="">All</option>
                    <option value="allowed">Allowed</option>
                    <option value="denied">Denied</option>
                    <option value="locked">Locked</option>
                </select>
            </div>
        </div>
    </div>

    {{-- Pending Requests --}}
    @if($pendingRequests->count() > 0)
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm mb-6">
            <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">
                    <i class="fa-solid fa-clock text-orange-500 mr-2"></i>
                    Pending Requests ({{ $pendingRequests->total() }})
                </h2>
                @if(count($selectedRequests) > 0)
                    <div class="flex gap-2">
                        <button wire:click="bulkTrain(true)"
                                class="btn bg-green-500 hover:bg-green-600 text-white text-sm">
                            <i class="fa-solid fa-check mr-1"></i> Allow Selected
                        </button>
                        <button wire:click="bulkTrain(false)"
                                class="btn bg-red-500 hover:bg-red-600 text-white text-sm">
                            <i class="fa-solid fa-ban mr-1"></i> Deny Selected
                        </button>
                    </div>
                @endif
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs text-gray-500 dark:text-gray-400 uppercase bg-gray-50 dark:bg-gray-700/50">
                            <th class="px-6 py-3 w-8">
                                <input type="checkbox"
                                       class="rounded border-gray-300 text-violet-600"
                                       wire:model.live="selectedRequests"
                                       @if($pendingRequests->pluck('id')->toArray() === $selectedRequests) checked @endif>
                            </th>
                            <th class="px-6 py-3">Entity</th>
                            <th class="px-6 py-3">Action</th>
                            <th class="px-6 py-3">Route</th>
                            <th class="px-6 py-3">Time</th>
                            <th class="px-6 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach($pendingRequests as $request)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-6 py-3">
                                    <input type="checkbox"
                                           value="{{ $request->id }}"
                                           wire:model.live="selectedRequests"
                                           class="rounded border-gray-300 text-violet-600">
                                </td>
                                <td class="px-6 py-3">
                                    <span class="font-medium text-gray-900 dark:text-gray-100">{{ $request->entity?->name ?? 'Unknown' }}</span>
                                    <div class="text-xs text-gray-500 font-mono">{{ $request->entity?->path }}</div>
                                </td>
                                <td class="px-6 py-3">
                                    <span class="font-mono text-gray-700 dark:text-gray-300">{{ $request->action }}</span>
                                    @if($request->scope)
                                        <span class="text-xs text-gray-500">({{ $request->scope }})</span>
                                    @endif
                                </td>
                                <td class="px-6 py-3 font-mono text-xs text-gray-500">
                                    {{ $request->method }} {{ Str::limit($request->route, 40) }}
                                </td>
                                <td class="px-6 py-3 text-gray-500 text-xs">
                                    {{ $request->created_at->diffForHumans() }}
                                </td>
                                <td class="px-6 py-3 text-right">
                                    <button wire:click="openTrain({{ $request->id }})"
                                            class="text-violet-600 hover:text-violet-700 font-medium">
                                        Train
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-6 py-4 border-t border-gray-100 dark:border-gray-700">
                {{ $pendingRequests->links() }}
            </div>
        </div>
    @endif

    {{-- Trained Permissions --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
        <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Trained Permissions</h2>
        </div>

        @if($permissions->isEmpty())
            <div class="p-12 text-center text-gray-500 dark:text-gray-400">
                <i class="fa-solid fa-shield text-4xl mb-4"></i>
                <p class="mb-4">No permissions trained yet</p>
                <p class="text-sm">Permissions will appear here as you train them through the matrix.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs text-gray-500 dark:text-gray-400 uppercase bg-gray-50 dark:bg-gray-700/50">
                            <th class="px-6 py-3">Entity</th>
                            <th class="px-6 py-3">Permission Key</th>
                            <th class="px-6 py-3">Scope</th>
                            <th class="px-6 py-3">Status</th>
                            <th class="px-6 py-3">Source</th>
                            <th class="px-6 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach($permissions as $permission)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-6 py-3">
                                    <span class="font-medium text-gray-900 dark:text-gray-100">{{ $permission->entity?->name ?? 'Unknown' }}</span>
                                    <div class="text-xs text-gray-500 font-mono">{{ $permission->entity?->path }}</div>
                                </td>
                                <td class="px-6 py-3 font-mono text-gray-700 dark:text-gray-300">
                                    {{ $permission->key }}
                                </td>
                                <td class="px-6 py-3 text-gray-500">
                                    {{ $permission->scope ?? 'global' }}
                                </td>
                                <td class="px-6 py-3">
                                    <div class="flex items-center gap-2">
                                        @if($permission->allowed)
                                            <span class="px-2 py-0.5 text-xs rounded-full bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400">
                                                Allowed
                                            </span>
                                        @else
                                            <span class="px-2 py-0.5 text-xs rounded-full bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400">
                                                Denied
                                            </span>
                                        @endif
                                        @if($permission->locked)
                                            <span class="px-2 py-0.5 text-xs rounded-full bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">
                                                <i class="fa-solid fa-lock mr-1"></i>Locked
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-3 text-xs text-gray-500">
                                    {{ $permission->source }}
                                    @if($permission->setByEntity)
                                        <span class="text-gray-400">by {{ $permission->setByEntity->code }}</span>
                                    @endif
                                </td>
                                <td class="px-6 py-3 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        @if($permission->locked)
                                            <button wire:click="unlockPermission({{ $permission->id }})"
                                                    class="p-2 text-amber-600 hover:text-amber-700"
                                                    title="Unlock">
                                                <i class="fa-solid fa-unlock text-sm"></i>
                                            </button>
                                        @endif
                                        @if(!$permission->locked)
                                            <button wire:click="deletePermission({{ $permission->id }})"
                                                    class="p-2 text-gray-400 hover:text-red-600"
                                                    title="Delete">
                                                <i class="fa-solid fa-trash text-sm"></i>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-6 py-4 border-t border-gray-100 dark:border-gray-700">
                {{ $permissions->links() }}
            </div>
        @endif
    </div>

    {{-- Training Modal --}}
    @if($showTrainModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-center justify-center min-h-screen p-4">
                <div class="fixed inset-0 bg-gray-500/75 dark:bg-gray-900/75 transition-opacity" wire:click="closeTrainModal"></div>

                <div class="inline-block bg-white dark:bg-gray-800 rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full sm:p-6">
                    <div class="mb-4">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Train Permission</h3>
                    </div>

                    <form wire:submit="train" class="space-y-4">
                        {{-- Entity --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Entity</label>
                            <select wire:model="trainingEntityId"
                                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                                <option value="">Select entity...</option>
                                @foreach($entities as $entity)
                                    <option value="{{ $entity->id }}">{{ $entity->path }} - {{ $entity->name }}</option>
                                @endforeach
                            </select>
                            @error('trainingEntityId') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
                        </div>

                        {{-- Permission Key --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Permission Key</label>
                            <input type="text"
                                   wire:model="trainingKey"
                                   class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 font-mono"
                                   placeholder="product.create">
                            <p class="mt-1 text-xs text-gray-500">e.g., product.create, order.view, refund.process</p>
                            @error('trainingKey') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
                        </div>

                        {{-- Scope --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Scope (optional)</label>
                            <input type="text"
                                   wire:model="trainingScope"
                                   class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                   placeholder="Leave empty for global">
                        </div>

                        {{-- Decision --}}
                        <div class="flex gap-4">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio"
                                       wire:model="trainingAllow"
                                       value="1"
                                       class="text-green-600 focus:ring-green-500">
                                <span class="text-sm text-gray-700 dark:text-gray-300">
                                    <i class="fa-solid fa-check text-green-600 mr-1"></i>Allow
                                </span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio"
                                       wire:model="trainingAllow"
                                       value="0"
                                       class="text-red-600 focus:ring-red-500">
                                <span class="text-sm text-gray-700 dark:text-gray-300">
                                    <i class="fa-solid fa-ban text-red-600 mr-1"></i>Deny
                                </span>
                            </label>
                        </div>

                        {{-- Lock --}}
                        <div class="border-t dark:border-gray-700 pt-4">
                            <label class="flex items-start gap-3 cursor-pointer">
                                <input type="checkbox"
                                       wire:model="trainingLock"
                                       class="mt-1 rounded border-gray-300 text-amber-600 focus:ring-amber-500">
                                <span class="text-sm text-gray-600 dark:text-gray-400">
                                    <span class="font-medium text-gray-900 dark:text-gray-100">Lock this permission</span>
                                    <br>
                                    Child entities cannot override this decision. Use for critical restrictions.
                                </span>
                            </label>
                        </div>

                        {{-- Actions --}}
                        <div class="flex justify-end gap-3 pt-4 border-t dark:border-gray-700">
                            <button type="button"
                                    wire:click="closeTrainModal"
                                    class="btn border-gray-200 dark:border-gray-600 hover:border-gray-300 text-gray-600 dark:text-gray-300">
                                Cancel
                            </button>
                            <button type="submit"
                                    class="btn bg-violet-500 hover:bg-violet-600 text-white">
                                Train Permission
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
