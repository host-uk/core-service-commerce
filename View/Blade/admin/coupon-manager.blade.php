<admin:module title="{{ __('commerce::commerce.coupons.title') }}" subtitle="{{ __('commerce::commerce.coupons.subtitle') }}">
    <x-slot:actions>
        <core:button wire:click="openBulkGenerate" icon="squares-plus" variant="ghost">{{ __('commerce::commerce.coupons.bulk.generate_button') }}</core:button>
        <core:button wire:click="openCreate" icon="plus">{{ __('commerce::commerce.actions.new_coupon') }}</core:button>
    </x-slot:actions>

    <admin:flash />

    <admin:filter-bar cols="3">
        <admin:search model="search" placeholder="{{ __('commerce::commerce.coupons.search_placeholder') }}" />
        <admin:filter model="statusFilter" :options="$this->statusOptions" placeholder="{{ __('commerce::commerce.coupons.all_coupons') }}" />
        <admin:clear-filters :fields="['search', 'statusFilter']" />
    </admin:filter-bar>

    <admin:manager-table
        :columns="$this->tableColumns"
        :rows="$this->tableRows"
        :rowIds="$this->tableRowIds"
        :pagination="$this->coupons"
        :selectable="true"
        :selected="$selected"
        empty="{{ __('commerce::commerce.coupons.empty') }}"
        emptyIcon="ticket"
    >
        <x-slot:bulkActions>
            <flux:button wire:click="exportSelected" size="sm" icon="arrow-down-tray">{{ __('commerce::commerce.bulk.export') }}</flux:button>
            <flux:button wire:click="bulkActivate" size="sm" icon="play">{{ __('commerce::commerce.bulk.activate') }}</flux:button>
            <flux:button wire:click="bulkDeactivate" size="sm" icon="pause">{{ __('commerce::commerce.bulk.deactivate') }}</flux:button>
            <flux:button wire:click="confirmBulkDelete" size="sm" variant="danger" icon="trash">{{ __('commerce::commerce.bulk.delete') }}</flux:button>
        </x-slot:bulkActions>
    </admin:manager-table>

    {{-- Bulk Delete Confirmation Modal --}}
    <core:modal wire:model="showBulkDeleteModal" class="max-w-md">
        <core:heading size="lg">{{ __('commerce::commerce.bulk.confirm_delete_title') }}</core:heading>

        <div class="mt-4 space-y-4">
            <flux:text>{{ __('commerce::commerce.bulk.confirm_delete_message', ['count' => count($selected)]) }}</flux:text>

            <div class="rounded-lg bg-amber-50 dark:bg-amber-900/20 p-3">
                <flux:text size="sm" class="text-amber-800 dark:text-amber-200">{{ __('commerce::commerce.bulk.delete_warning') }}</flux:text>
            </div>

            <div class="flex justify-end gap-2 pt-4">
                <flux:button variant="ghost" wire:click="closeBulkDeleteModal">{{ __('commerce::commerce.actions.cancel') }}</flux:button>
                <flux:button variant="danger" wire:click="bulkDelete">{{ __('commerce::commerce.bulk.delete') }}</flux:button>
            </div>
        </div>
    </core:modal>

    {{-- Bulk Generate Modal --}}
    <core:modal wire:model="showBulkGenerateModal" class="max-w-2xl">
        <core:heading size="lg">{{ __('commerce::commerce.coupons.bulk.modal_title') }}</core:heading>

        <form wire:submit="generateBulk" class="mt-4 space-y-6">
            {{-- Generation Settings --}}
            <div class="space-y-4">
                <flux:heading size="sm" class="text-zinc-700 dark:text-zinc-300">{{ __('commerce::commerce.coupons.bulk.generation_settings') }}</flux:heading>
                <div class="grid grid-cols-2 gap-4">
                    <flux:input wire:model="bulk_count" label="{{ __('commerce::commerce.coupons.bulk.count') }}" type="number" min="1" max="100" required />
                    <flux:input wire:model="bulk_code_prefix" label="{{ __('commerce::commerce.coupons.bulk.code_prefix') }}" placeholder="{{ __('commerce::commerce.coupons.bulk.code_prefix_placeholder') }}" class="font-mono uppercase" />
                </div>
                <flux:input wire:model="bulk_name" label="{{ __('commerce::commerce.coupons.form.name') }}" placeholder="{{ __('commerce::commerce.coupons.form.name_placeholder') }}" required />
            </div>

            <flux:separator />

            {{-- Discount Settings Section --}}
            <div class="space-y-4">
                <flux:heading size="sm" class="text-zinc-700 dark:text-zinc-300">{{ __('commerce::commerce.coupons.sections.discount_settings') }}</flux:heading>
                <div class="grid grid-cols-2 gap-4">
                    <flux:select wire:model="bulk_type" label="{{ __('commerce::commerce.coupons.form.discount_type') }}">
                        <flux:select.option value="percentage">{{ __('commerce::commerce.coupons.form.percentage') }}</flux:select.option>
                        <flux:select.option value="fixed_amount">{{ __('commerce::commerce.coupons.form.fixed_amount') }}</flux:select.option>
                    </flux:select>
                    <flux:input wire:model="bulk_value" label="{{ $bulk_type === 'percentage' ? __('commerce::commerce.coupons.form.discount_percent') : __('commerce::commerce.coupons.form.discount_amount') }}" type="number" step="0.01" min="0.01" required />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <flux:input wire:model="bulk_min_amount" label="{{ __('commerce::commerce.coupons.form.min_amount') }}" type="number" step="0.01" min="0" placeholder="0.00" />
                    <flux:input wire:model="bulk_max_discount" label="{{ __('commerce::commerce.coupons.form.max_discount') }}" type="number" step="0.01" min="0" placeholder="{{ __('commerce::commerce.coupons.form.no_limit') }}" />
                </div>
            </div>

            <flux:separator />

            {{-- Applicability Section --}}
            <div class="space-y-4">
                <flux:heading size="sm" class="text-zinc-700 dark:text-zinc-300">{{ __('commerce::commerce.coupons.sections.applicability') }}</flux:heading>
                <div class="grid grid-cols-2 gap-4">
                    <flux:select wire:model.live="bulk_applies_to" label="{{ __('commerce::commerce.coupons.form.applies_to') }}">
                        <flux:select.option value="all">{{ __('commerce::commerce.coupons.form.all_packages') }}</flux:select.option>
                        <flux:select.option value="packages">{{ __('commerce::commerce.coupons.form.specific_packages') }}</flux:select.option>
                    </flux:select>

                    @if ($bulk_applies_to === 'packages')
                        <flux:field>
                            <flux:label>{{ __('commerce::commerce.coupons.form.packages') }}</flux:label>
                            <div class="max-h-32 space-y-1 overflow-y-auto rounded-lg border border-zinc-200 dark:border-zinc-700 p-2">
                                @foreach ($this->packages as $package)
                                    <flux:checkbox wire:model="bulk_package_ids" value="{{ $package->id }}" label="{{ $package->name }}" />
                                @endforeach
                            </div>
                        </flux:field>
                    @endif
                </div>
            </div>

            <flux:separator />

            {{-- Usage Limits Section --}}
            <div class="space-y-4">
                <flux:heading size="sm" class="text-zinc-700 dark:text-zinc-300">{{ __('commerce::commerce.coupons.sections.usage_limits') }}</flux:heading>
                <div class="grid grid-cols-3 gap-4">
                    <flux:input wire:model="bulk_max_uses" label="{{ __('commerce::commerce.coupons.form.max_uses') }}" type="number" min="1" placeholder="{{ __('commerce::commerce.coupons.form.unlimited') }}" />
                    <flux:input wire:model="bulk_max_uses_per_workspace" label="{{ __('commerce::commerce.coupons.form.max_uses_per_workspace') }}" type="number" min="1" required />
                    <flux:select wire:model.live="bulk_duration" label="{{ __('commerce::commerce.coupons.form.duration') }}">
                        <flux:select.option value="once">{{ __('commerce::commerce.coupons.form.apply_once') }}</flux:select.option>
                        <flux:select.option value="repeating">{{ __('commerce::commerce.coupons.form.apply_repeating') }}</flux:select.option>
                        <flux:select.option value="forever">{{ __('commerce::commerce.coupons.form.apply_forever') }}</flux:select.option>
                    </flux:select>
                </div>
                @if ($bulk_duration === 'repeating')
                    <flux:input wire:model="bulk_duration_months" label="{{ __('commerce::commerce.coupons.form.duration_months') }}" type="number" min="1" max="24" class="max-w-xs" />
                @endif
            </div>

            <flux:separator />

            {{-- Validity Period Section --}}
            <div class="space-y-4">
                <flux:heading size="sm" class="text-zinc-700 dark:text-zinc-300">{{ __('commerce::commerce.coupons.sections.validity_period') }}</flux:heading>
                <div class="grid grid-cols-2 gap-4">
                    <flux:input wire:model="bulk_valid_from" label="{{ __('commerce::commerce.coupons.form.valid_from') }}" type="date" />
                    <flux:input wire:model="bulk_valid_until" label="{{ __('commerce::commerce.coupons.form.valid_until') }}" type="date" />
                </div>
            </div>

            <flux:separator />

            {{-- Status Section --}}
            <div class="flex items-center justify-between">
                <flux:checkbox wire:model="bulk_is_active" label="{{ __('commerce::commerce.form.active') }}" />
            </div>

            <div class="flex justify-end gap-2 pt-4">
                <flux:button variant="ghost" wire:click="closeBulkGenerateModal">{{ __('commerce::commerce.actions.cancel') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('commerce::commerce.coupons.bulk.generate_action') }}</flux:button>
            </div>
        </form>
    </core:modal>

    {{-- Create/Edit Coupon Modal --}}
    <core:modal wire:model="showModal" class="max-w-2xl">
        <core:heading size="lg">{{ $editingId ? __('commerce::commerce.coupons.modal.edit_title') : __('commerce::commerce.coupons.modal.create_title') }}</core:heading>

        <form wire:submit="save" class="mt-4 space-y-6">
            {{-- Basic Information Section --}}
            <div class="space-y-4">
                <flux:heading size="sm" class="text-zinc-700 dark:text-zinc-300">{{ __('commerce::commerce.coupons.sections.basic_info') }}</flux:heading>
                <div class="grid grid-cols-2 gap-4">
                    <flux:input wire:model="code" label="{{ __('commerce::commerce.coupons.form.code') }}" placeholder="{{ __('commerce::commerce.coupons.form.code_placeholder') }}" required class="font-mono uppercase" />
                    <flux:input wire:model="name" label="{{ __('commerce::commerce.coupons.form.name') }}" placeholder="{{ __('commerce::commerce.coupons.form.name_placeholder') }}" required />
                </div>
                <flux:textarea wire:model="description" label="{{ __('commerce::commerce.coupons.form.description') }}" rows="2" />
            </div>

            <flux:separator />

            {{-- Discount Settings Section --}}
            <div class="space-y-4">
                <flux:heading size="sm" class="text-zinc-700 dark:text-zinc-300">{{ __('commerce::commerce.coupons.sections.discount_settings') }}</flux:heading>
                <div class="grid grid-cols-2 gap-4">
                    <flux:select wire:model="type" label="{{ __('commerce::commerce.coupons.form.discount_type') }}">
                        <flux:select.option value="percentage">{{ __('commerce::commerce.coupons.form.percentage') }}</flux:select.option>
                        <flux:select.option value="fixed_amount">{{ __('commerce::commerce.coupons.form.fixed_amount') }}</flux:select.option>
                    </flux:select>
                    <flux:input wire:model="value" label="{{ $type === 'percentage' ? __('commerce::commerce.coupons.form.discount_percent') : __('commerce::commerce.coupons.form.discount_amount') }}" type="number" step="0.01" min="0.01" required />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <flux:input wire:model="min_amount" label="{{ __('commerce::commerce.coupons.form.min_amount') }}" type="number" step="0.01" min="0" placeholder="0.00" />
                    <flux:input wire:model="max_discount" label="{{ __('commerce::commerce.coupons.form.max_discount') }}" type="number" step="0.01" min="0" placeholder="{{ __('commerce::commerce.coupons.form.no_limit') }}" />
                </div>
            </div>

            <flux:separator />

            {{-- Applicability Section --}}
            <div class="space-y-4">
                <flux:heading size="sm" class="text-zinc-700 dark:text-zinc-300">{{ __('commerce::commerce.coupons.sections.applicability') }}</flux:heading>
                <div class="grid grid-cols-2 gap-4">
                    <flux:select wire:model.live="applies_to" label="{{ __('commerce::commerce.coupons.form.applies_to') }}">
                        <flux:select.option value="all">{{ __('commerce::commerce.coupons.form.all_packages') }}</flux:select.option>
                        <flux:select.option value="packages">{{ __('commerce::commerce.coupons.form.specific_packages') }}</flux:select.option>
                    </flux:select>

                    @if ($applies_to === 'packages')
                        <flux:field>
                            <flux:label>{{ __('commerce::commerce.coupons.form.packages') }}</flux:label>
                            <div class="max-h-32 space-y-1 overflow-y-auto rounded-lg border border-zinc-200 dark:border-zinc-700 p-2">
                                @foreach ($this->packages as $package)
                                    <flux:checkbox wire:model="package_ids" value="{{ $package->id }}" label="{{ $package->name }}" />
                                @endforeach
                            </div>
                        </flux:field>
                    @endif
                </div>
            </div>

            <flux:separator />

            {{-- Usage Limits Section --}}
            <div class="space-y-4">
                <flux:heading size="sm" class="text-zinc-700 dark:text-zinc-300">{{ __('commerce::commerce.coupons.sections.usage_limits') }}</flux:heading>
                <div class="grid grid-cols-3 gap-4">
                    <flux:input wire:model="max_uses" label="{{ __('commerce::commerce.coupons.form.max_uses') }}" type="number" min="1" placeholder="{{ __('commerce::commerce.coupons.form.unlimited') }}" />
                    <flux:input wire:model="max_uses_per_workspace" label="{{ __('commerce::commerce.coupons.form.max_uses_per_workspace') }}" type="number" min="1" required />
                    <flux:select wire:model.live="duration" label="{{ __('commerce::commerce.coupons.form.duration') }}">
                        <flux:select.option value="once">{{ __('commerce::commerce.coupons.form.apply_once') }}</flux:select.option>
                        <flux:select.option value="repeating">{{ __('commerce::commerce.coupons.form.apply_repeating') }}</flux:select.option>
                        <flux:select.option value="forever">{{ __('commerce::commerce.coupons.form.apply_forever') }}</flux:select.option>
                    </flux:select>
                </div>
                @if ($duration === 'repeating')
                    <flux:input wire:model="duration_months" label="{{ __('commerce::commerce.coupons.form.duration_months') }}" type="number" min="1" max="24" class="max-w-xs" />
                @endif
            </div>

            <flux:separator />

            {{-- Validity Period Section --}}
            <div class="space-y-4">
                <flux:heading size="sm" class="text-zinc-700 dark:text-zinc-300">{{ __('commerce::commerce.coupons.sections.validity_period') }}</flux:heading>
                <div class="grid grid-cols-2 gap-4">
                    <flux:input wire:model="valid_from" label="{{ __('commerce::commerce.coupons.form.valid_from') }}" type="date" />
                    <flux:input wire:model="valid_until" label="{{ __('commerce::commerce.coupons.form.valid_until') }}" type="date" />
                </div>
            </div>

            <flux:separator />

            {{-- Status Section --}}
            <div class="flex items-center justify-between">
                <flux:checkbox wire:model="is_active" label="{{ __('commerce::commerce.form.active') }}" />
            </div>

            <div class="flex justify-end gap-2 pt-4">
                <flux:button variant="ghost" wire:click="closeModal">{{ __('commerce::commerce.actions.cancel') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ $editingId ? __('commerce::commerce.coupons.actions.update') : __('commerce::commerce.coupons.actions.create') }}</flux:button>
            </div>
        </form>
    </core:modal>
</admin:module>
