<div x-data="{ highlighted: 0 }">
    @if ($searching)
        <div class="relative">
            <flux:input
                wire:model.live.debounce.200ms="search"
                placeholder="Buscar por nombre o documento..."
                icon="magnifying-glass"
                autocomplete="off"
                x-on:input="highlighted = 0"
                x-on:keydown.arrow-down.prevent="highlighted = Math.min(highlighted + 1, ($refs.resultsList?.children.length ?? 1) - 1)"
                x-on:keydown.arrow-up.prevent="highlighted = Math.max(highlighted - 1, 0)"
                x-on:keydown.enter.prevent="$refs.resultsList?.children[highlighted]?.click()"
            />

            @if (mb_strlen($search) >= 2)
                <div x-ref="resultsList"
                    class="absolute z-20 mt-1 w-full rounded-xl border border-zinc-200 bg-white p-1 shadow-xl dark:border-zinc-700 dark:bg-zinc-900">
                    @forelse ($this->results as $i => $result)
                        <div
                            wire:click="select({{ $result->id }}, '{{ addslashes($result->label) }}', '{{ addslashes($result->subtitle ?? '') }}', {{ \Illuminate\Support\Js::from($result->meta) }})"
                            :class="highlighted === {{ $i }} ? 'bg-zinc-50 dark:bg-zinc-800' : ''"
                            class="flex cursor-pointer items-center gap-3 rounded-lg p-2 text-left hover:bg-zinc-50 dark:hover:bg-zinc-800"
                        >
                            @if ($result->initials)
                                <span class="grid size-8 shrink-0 place-items-center rounded-lg text-[10px] font-bold {{ $result->badgeColor }}">
                                    {{ $result->initials }}
                                </span>
                            @endif
                            <span class="min-w-0">
                                <span class="block truncate text-xs font-semibold text-zinc-900 dark:text-zinc-100">{{ $result->label }}</span>
                                @if ($result->subtitle)
                                    <span class="block truncate text-[11px] text-zinc-500">{{ $result->subtitle }}</span>
                                @endif
                            </span>
                        </div>
                    @empty
                        <div class="px-3 py-2 text-sm text-zinc-500">Sin resultados</div>
                    @endforelse
                </div>
            @endif
        </div>
    @else
        <div class="flex items-center justify-between gap-3 rounded-xl border border-zinc-200 px-3 py-2 dark:border-zinc-700">
            <div class="flex min-w-0 items-center gap-3">
                @if ($selectedLabel)
                    <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-violet-100 text-[10px] font-bold text-violet-700 dark:bg-violet-950/60 dark:text-violet-300">
                        {{ strtoupper(substr($selectedLabel, 0, 2)) }}
                    </span>
                @endif
                <span class="min-w-0">
                    <span class="block truncate text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $selectedLabel }}</span>
                    @if ($selectedSubtitle)
                        <span class="block truncate text-xs text-zinc-500">{{ $selectedSubtitle }}</span>
                    @endif
                </span>
            </div>
            <button type="button" wire:click="clear" class="ml-2 shrink-0 text-zinc-400 hover:text-zinc-600" title="Cambiar">
                <flux:icon name="x-mark" class="size-4" />
            </button>
        </div>
    @endif
</div>