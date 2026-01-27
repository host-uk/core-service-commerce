<admin:module title="{{ __('commerce::commerce.dashboard.title') }}" subtitle="{{ __('commerce::commerce.dashboard.subtitle') }}">
    <x-slot:actions>
        <flux:button href="{{ route('hub.commerce.orders') }}" wire:navigate variant="primary" icon="list-bullet">
            {{ __('commerce::commerce.actions.view_orders') }}
        </flux:button>
    </x-slot:actions>

    <admin:stats :items="$this->statCards" />

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Quick Actions with Visual Icon Cards --}}
        <div class="bg-white dark:bg-zinc-800 shadow-xs rounded-xl">
            <header class="px-5 py-4 border-b border-zinc-100 dark:border-zinc-700/60">
                <h2 class="font-semibold text-zinc-800 dark:text-zinc-100">{{ __('commerce::commerce.sections.quick_actions') }}</h2>
            </header>
            <div class="p-5">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    @foreach($this->quickActions as $action)
                        <a href="{{ $action['href'] }}" wire:navigate class="flex items-center p-3 bg-zinc-50 dark:bg-zinc-700/30 rounded-lg hover:bg-zinc-100 dark:hover:bg-zinc-700/50 transition group">
                            <div class="w-10 h-10 rounded-full bg-{{ $action['color'] ?? 'violet' }}-100 dark:bg-{{ $action['color'] ?? 'violet' }}-500/20 flex items-center justify-center mr-3 group-hover:scale-105 transition-transform">
                                <core:icon :name="$action['icon']" class="w-5 h-5 text-{{ $action['color'] ?? 'violet' }}-500" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="font-medium text-zinc-800 dark:text-zinc-100 truncate">{{ $action['title'] }}</div>
                                <div class="text-sm text-zinc-500 dark:text-zinc-400 truncate">{{ $action['subtitle'] }}</div>
                            </div>
                            <core:icon name="chevron-right" class="w-4 h-4 text-zinc-400 dark:text-zinc-500 opacity-0 group-hover:opacity-100 transition-opacity ml-2" />
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        <admin:data-table
            title="{{ __('commerce::commerce.sections.recent_orders') }}"
            :action="route('hub.commerce.orders')"
            :columns="[__('commerce::commerce.table.order'), __('commerce::commerce.table.workspace'), __('commerce::commerce.table.status'), ['label' => __('commerce::commerce.table.total'), 'align' => 'right']]"
            :rows="$this->orderRows"
            empty="{{ __('commerce::commerce.orders.empty_dashboard') }}"
            emptyIcon="shopping-cart"
            class="lg:col-span-2"
        />
    </div>
</admin:module>
