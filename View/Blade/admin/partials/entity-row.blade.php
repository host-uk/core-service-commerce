@php
    $typeConfig = match($entity->type) {
        'm1' => ['icon' => 'fa-building', 'bg' => 'bg-blue-100 dark:bg-blue-900/30', 'text' => 'text-blue-600 dark:text-blue-400', 'badge' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400'],
        'm2' => ['icon' => 'fa-store', 'bg' => 'bg-orange-100 dark:bg-orange-900/30', 'text' => 'text-orange-600 dark:text-orange-400', 'badge' => 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400'],
        'm3' => ['icon' => 'fa-truck', 'bg' => 'bg-green-100 dark:bg-green-900/30', 'text' => 'text-green-600 dark:text-green-400', 'badge' => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'],
        default => ['icon' => 'fa-cube', 'bg' => 'bg-gray-100 dark:bg-gray-700', 'text' => 'text-gray-600 dark:text-gray-400', 'badge' => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-400'],
    };
    $indent = $level * 2; // rem units
@endphp

<div class="px-6 py-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition {{ !$entity->is_active ? 'opacity-50' : '' }}"
     style="padding-left: {{ 1.5 + $indent }}rem;">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            {{-- Tree connector for children --}}
            @if($level > 0)
                <span class="text-gray-300 dark:text-gray-600">
                    @if($level === 1)
                        <i class="fa-solid fa-turn-up fa-rotate-90 text-xs"></i>
                    @else
                        <i class="fa-solid fa-turn-up fa-rotate-90 text-xs ml-4"></i>
                    @endif
                </span>
            @endif

            {{-- Type icon --}}
            <div class="w-8 h-8 rounded-full {{ $typeConfig['bg'] }} flex items-center justify-center flex-shrink-0">
                <i class="fa-solid {{ $typeConfig['icon'] }} {{ $typeConfig['text'] }} text-sm"></i>
            </div>

            {{-- Entity info --}}
            <div>
                <div class="flex items-center gap-2">
                    <span class="font-medium text-gray-900 dark:text-gray-100">{{ $entity->name }}</span>
                    <span class="px-2 py-0.5 text-xs rounded-full font-mono {{ $typeConfig['badge'] }}">
                        {{ strtoupper($entity->type) }}
                    </span>
                    @if(!$entity->is_active)
                        <span class="px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400">
                            Inactive
                        </span>
                    @endif
                </div>
                <div class="flex items-center gap-3 text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                    <span class="font-mono">{{ $entity->code }}</span>
                    @if($entity->domain)
                        <span><i class="fa-solid fa-globe mr-1"></i>{{ $entity->domain }}</span>
                    @endif
                    <span class="font-mono text-gray-400 dark:text-gray-500">{{ $entity->path }}</span>
                </div>
            </div>
        </div>

        {{-- Actions --}}
        <div class="flex items-center gap-2">
            {{-- Add child (only M1 and M2 can have children) --}}
            @if(in_array($entity->type, ['m1', 'm2']))
                <button wire:click="openCreate({{ $entity->id }})"
                        class="p-2 text-gray-400 hover:text-violet-600 dark:hover:text-violet-400 transition"
                        title="Add child entity">
                    <i class="fa-solid fa-plus text-sm"></i>
                </button>
            @endif

            {{-- Toggle active --}}
            <button wire:click="toggleActive({{ $entity->id }})"
                    class="p-2 text-gray-400 hover:text-{{ $entity->is_active ? 'red' : 'green' }}-600 transition"
                    title="{{ $entity->is_active ? 'Deactivate' : 'Activate' }}">
                <i class="fa-solid fa-{{ $entity->is_active ? 'pause' : 'play' }} text-sm"></i>
            </button>

            {{-- Edit --}}
            <button wire:click="openEdit({{ $entity->id }})"
                    class="p-2 text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 transition"
                    title="Edit entity">
                <i class="fa-solid fa-pen text-sm"></i>
            </button>

            {{-- Delete --}}
            <button wire:click="confirmDelete({{ $entity->id }})"
                    class="p-2 text-gray-400 hover:text-red-600 dark:hover:text-red-400 transition"
                    title="Delete entity">
                <i class="fa-solid fa-trash text-sm"></i>
            </button>
        </div>
    </div>
</div>
