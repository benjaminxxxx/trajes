{{-- Producto existente en el asistente "¿Qué vas a registrar?": para reconocerlo y usarlo en vez de duplicarlo. --}}

<div class="flex items-center gap-3 rounded-xl border border-zinc-200 p-2.5 dark:border-zinc-700">
    @if ($product->primaryMedia)
        <img src="{{ Storage::url($product->primaryMedia->file_path) }}" alt=""
            class="size-12 shrink-0 rounded-lg object-cover ring-1 ring-zinc-200 dark:ring-zinc-700" />
    @else
        <span class="grid size-12 shrink-0 place-items-center rounded-lg bg-zinc-100 text-zinc-400 dark:bg-zinc-800">
            <flux:icon icon="photo" class="size-5" />
        </span>
    @endif

    <div class="min-w-0 flex-1">
        <p class="truncate text-sm font-semibold text-zinc-900 dark:text-white">{{ $product->name }}</p>
        <p class="truncate text-xs text-zinc-500">
            @if (($showCategory ?? false) && $product->category)
                {{ $product->category->name }} ·
            @endif
            {{ $product->brand?->name ?? 'Sin marca' }} · Stock {{ (int) $product->stock_total }}
        </p>
        @if ($summary = $product->characteristicsSummary())
            <p class="truncate text-xs text-violet-600 dark:text-violet-400">{{ $summary }}</p>
        @endif
    </div>

    <div class="flex shrink-0 flex-col gap-1 sm:flex-row">
        @if ($context === 'purchase')
            <flux:button size="sm" variant="primary" icon="plus" wire:click="useExisting({{ $product->id }})">
                Agregar
            </flux:button>
        @else
            @can('compras.registrar')
                <flux:button size="sm" variant="primary" icon="shopping-cart" wire:click="useExisting({{ $product->id }})">
                    Comprar más
                </flux:button>
            @endcan
        @endif
        <flux:button size="sm" variant="ghost" icon="pencil" wire:click="open({{ $product->id }}, '{{ $context }}')"
            aria-label="Editar {{ $product->name }}" />
    </div>
</div>
