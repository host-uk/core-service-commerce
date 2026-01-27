<admin:module title="{{ __('commerce::commerce.products.title') }}" subtitle="{{ __('commerce::commerce.products.subtitle') }}">
    <x-slot name="actions">
        <flux:button href="{{ route('hub.commerce.entities') }}" variant="ghost" size="sm" icon="arrow-left">
            {{ __('commerce::commerce.actions.entity_hierarchy') }}
        </flux:button>
        @if($this->selectedEntity?->isM1())
            <flux:button wire:click="openCreate" variant="primary" size="sm" icon="plus">
                {{ __('commerce::commerce.actions.add_product') }}
            </flux:button>
        @endif
    </x-slot>

    <admin:flash />

    <admin:filter-bar cols="4">
        <admin:filter model="entityId" :options="$this->entityOptions" placeholder="{{ __('commerce::commerce.filters.all_entities') }}" label="{{ __('commerce::commerce.filters.entity') }}" />
        <admin:search model="search" placeholder="{{ __('commerce::commerce.filters.search_placeholder') }}" />
        <admin:filter model="category" :options="$this->categories" placeholder="{{ __('commerce::commerce.filters.all_categories') }}" />
        <admin:filter model="stockFilter" :options="$this->stockFilters" placeholder="{{ __('commerce::commerce.filters.all') }}" />
    </admin:filter-bar>

    <admin:manager-table
        :columns="$this->tableColumns"
        :rows="$this->tableRows"
        :pagination="$this->products"
        empty="{{ $entityId ? __('commerce::commerce.products.empty') : __('commerce::commerce.products.empty_no_entity') }}"
        emptyIcon="cube"
    />

    {{-- Product Create/Edit Modal --}}
    <core:modal wire:model="showModal" class="max-w-2xl">
        <core:heading size="lg">
            {{ $editingId ? __('commerce::commerce.products.modal.edit_title') : __('commerce::commerce.products.modal.create_title') }}
        </core:heading>

        <form wire:submit="save" class="mt-4 space-y-6">
            <div class="grid grid-cols-2 gap-4">
                <core:input wire:model="form.sku" label="{{ __('commerce::commerce.form.sku') }}" :disabled="(bool) $editingId" />
                <core:select wire:model="form.type" label="{{ __('commerce::commerce.form.type') }}">
                    <option value="simple">{{ __('commerce::commerce.product_types.simple') }}</option>
                    <option value="variable">{{ __('commerce::commerce.product_types.variable') }}</option>
                    <option value="bundle">{{ __('commerce::commerce.product_types.bundle') }}</option>
                    <option value="virtual">{{ __('commerce::commerce.product_types.virtual') }}</option>
                    <option value="subscription">{{ __('commerce::commerce.product_types.subscription') }}</option>
                </core:select>
            </div>

            <core:input wire:model="form.name" label="{{ __('commerce::commerce.form.name') }}" />

            <core:textarea wire:model="form.description" label="{{ __('commerce::commerce.form.description') }}" rows="3" />

            <div class="grid grid-cols-2 gap-4">
                <core:input wire:model="form.category" label="{{ __('commerce::commerce.form.category') }}" />
                <core:input wire:model="form.subcategory" label="{{ __('commerce::commerce.form.subcategory') }}" />
            </div>

            <div class="grid grid-cols-3 gap-4">
                <core:input wire:model="form.price" type="number" min="0" label="{{ __('commerce::commerce.form.price') }}" />
                <core:input wire:model="form.cost_price" type="number" min="0" label="{{ __('commerce::commerce.form.cost_price') }}" />
                <core:input wire:model="form.rrp" type="number" min="0" label="{{ __('commerce::commerce.form.rrp') }}" />
            </div>

            <div class="grid grid-cols-3 gap-4">
                <core:input wire:model="form.stock_quantity" type="number" min="0" label="{{ __('commerce::commerce.form.stock_quantity') }}" />
                <core:input wire:model="form.low_stock_threshold" type="number" min="0" label="{{ __('commerce::commerce.form.low_stock_threshold') }}" />
                <core:select wire:model="form.tax_class" label="{{ __('commerce::commerce.form.tax_class') }}">
                    <option value="standard">{{ __('commerce::commerce.tax_classes.standard') }}</option>
                    <option value="reduced">{{ __('commerce::commerce.tax_classes.reduced') }}</option>
                    <option value="zero">{{ __('commerce::commerce.tax_classes.zero') }}</option>
                    <option value="exempt">{{ __('commerce::commerce.tax_classes.exempt') }}</option>
                </core:select>
            </div>

            <div class="flex flex-wrap gap-6">
                <core:checkbox wire:model="form.track_stock" label="{{ __('commerce::commerce.form.track_stock') }}" />
                <core:checkbox wire:model="form.allow_backorder" label="{{ __('commerce::commerce.form.allow_backorder') }}" />
                <core:checkbox wire:model="form.is_active" label="{{ __('commerce::commerce.form.active') }}" />
                <core:checkbox wire:model="form.is_featured" label="{{ __('commerce::commerce.form.featured') }}" />
                <core:checkbox wire:model="form.is_visible" label="{{ __('commerce::commerce.form.visible') }}" />
            </div>

            <div class="flex justify-end gap-2 pt-4 border-t border-gray-200 dark:border-gray-700">
                <flux:button variant="ghost" wire:click="$set('showModal', false)">{{ __('commerce::commerce.actions.cancel') }}</flux:button>
                <flux:button type="submit" variant="primary">
                    {{ $editingId ? __('commerce::commerce.products.actions.update') : __('commerce::commerce.products.actions.create') }}
                </flux:button>
            </div>
        </form>
    </core:modal>

    {{-- Assignment Modal --}}
    <core:modal wire:model="showAssignModal" class="max-w-lg">
        <core:heading size="lg">{{ __('commerce::commerce.assignments.title') }}</core:heading>

        <form wire:submit="saveAssignment" class="mt-4 space-y-4">
            <core:select wire:model="assignForm.entity_id" label="{{ __('commerce::commerce.assignments.entity') }}">
                <option value="">{{ __('commerce::commerce.assignments.select_entity') }}</option>
                @foreach($this->allEntities as $entity)
                    @if(!$entity->isM1())
                        <option value="{{ $entity->id }}">
                            {{ str_repeat('— ', $entity->depth) }}{{ $entity->code }} ({{ strtoupper($entity->type) }})
                        </option>
                    @endif
                @endforeach
            </core:select>

            <div class="grid grid-cols-2 gap-4">
                <core:input wire:model="assignForm.price_override" type="number" min="0" label="{{ __('commerce::commerce.assignments.price_override') }}" placeholder="{{ __('commerce::commerce.assignments.price_placeholder') }}" />
                <core:input wire:model="assignForm.margin_percent" type="number" min="0" max="100" step="0.01" label="{{ __('commerce::commerce.assignments.margin_percent') }}" />
            </div>

            <core:input wire:model="assignForm.name_override" label="{{ __('commerce::commerce.assignments.name_override') }}" placeholder="{{ __('commerce::commerce.assignments.name_placeholder') }}" />

            <div class="flex gap-4">
                <core:checkbox wire:model="assignForm.is_active" label="{{ __('commerce::commerce.form.active') }}" />
                <core:checkbox wire:model="assignForm.is_featured" label="{{ __('commerce::commerce.form.featured') }}" />
            </div>

            <div class="flex justify-end gap-2 pt-4 border-t border-gray-200 dark:border-gray-700">
                <flux:button variant="ghost" wire:click="$set('showAssignModal', false)">{{ __('commerce::commerce.actions.cancel') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('commerce::commerce.actions.assign') }}</flux:button>
            </div>
        </form>
    </core:modal>
</admin:module>
