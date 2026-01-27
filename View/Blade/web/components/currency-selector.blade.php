@if ($style === 'dropdown')
    <div
        x-data="{ open: false }"
        @click.outside="open = false"
        class="relative"
    >
        {{-- Trigger Button --}}
        <button
            @click="open = !open"
            type="button"
            @class([
                'flex items-center gap-2 rounded-lg transition-colors',
                'px-3 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700' => !$compact,
                'px-2 py-1 text-xs hover:bg-zinc-100 dark:hover:bg-zinc-700' => $compact,
            ])
        >
            @if ($showFlags && $this->currentCurrency['flag'])
                <span class="text-base">
                    @if (in_array($this->currentCurrency['flag'], ['eu']))
                        <i class="fa-solid fa-euro-sign"></i>
                    @else
                        <span class="fi fi-{{ $this->currentCurrency['flag'] }}"></span>
                    @endif
                </span>
            @endif

            <span class="font-medium text-zinc-700 dark:text-zinc-300">
                {{ $this->currentCurrency['symbol'] }}
                @if ($showNames && !$compact)
                    <span class="text-zinc-500 dark:text-zinc-400">{{ $this->currentCurrency['code'] }}</span>
                @endif
            </span>

            <i class="fa-solid fa-chevron-down text-[0.6rem] text-zinc-400 transition-transform" :class="{ 'rotate-180': open }"></i>
        </button>

        {{-- Dropdown Menu --}}
        <div
            x-show="open"
            x-transition:enter="transition ease-out duration-100"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            @class([
                'absolute z-50 mt-2 w-48 rounded-lg bg-white dark:bg-zinc-800 shadow-lg ring-1 ring-black ring-opacity-5 dark:ring-zinc-700',
                'right-0' => true,
            ])
            style="display: none;"
        >
            <div class="py-1">
                @foreach ($this->currencies as $code => $currency)
                    <button
                        wire:click="selectCurrency('{{ $code }}')"
                        @click="open = false"
                        @class([
                            'w-full flex items-center gap-3 px-4 py-2 text-sm transition-colors',
                            'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-300' => $selected === $code,
                            'text-zinc-700 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-700' => $selected !== $code,
                        ])
                    >
                        @if ($showFlags && $currency['flag'])
                            <span class="w-5 text-center">
                                @if ($currency['flag'] === 'eu')
                                    <i class="fa-solid fa-euro-sign text-blue-500"></i>
                                @else
                                    <span class="fi fi-{{ $currency['flag'] }}"></span>
                                @endif
                            </span>
                        @endif

                        <span class="flex-1 text-left">
                            {{ $currency['symbol'] }}
                            @if ($showNames)
                                <span class="text-zinc-500 dark:text-zinc-400 ml-1">{{ $currency['name'] }}</span>
                            @else
                                <span class="text-zinc-500 dark:text-zinc-400 ml-1">{{ $code }}</span>
                            @endif
                        </span>

                        @if ($selected === $code)
                            <i class="fa-solid fa-check text-indigo-600 dark:text-indigo-400"></i>
                        @endif
                    </button>
                @endforeach
            </div>
        </div>
    </div>
@else
    {{-- Inline Buttons Style --}}
    <div class="flex items-center gap-1">
        @foreach ($this->currencies as $code => $currency)
            <button
                wire:click="selectCurrency('{{ $code }}')"
                @class([
                    'px-3 py-1.5 rounded-lg text-sm font-medium transition-colors',
                    'bg-indigo-600 text-white' => $selected === $code,
                    'text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-700' => $selected !== $code,
                ])
                title="{{ $currency['name'] }}"
            >
                @if ($showFlags && $currency['flag'])
                    <span class="mr-1">
                        @if ($currency['flag'] === 'eu')
                            <i class="fa-solid fa-euro-sign"></i>
                        @else
                            <span class="fi fi-{{ $currency['flag'] }}"></span>
                        @endif
                    </span>
                @endif
                {{ $currency['symbol'] }}
            </button>
        @endforeach
    </div>
@endif
