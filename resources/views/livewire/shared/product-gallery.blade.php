<div>
    <div class="mb-3 flex flex-col gap-3 sm:flex-row">
        <div class="relative flex-1">
            <flux:input wire:model.live.debounce.400ms="search" placeholder="Busca: mujer rojo L quinceañera satín..."
                icon="magnifying-glass" clearable />
        </div>
        <flux:button
            :variant="$onlyAvailable ? 'primary' : 'outline'"
            icon="check"
            wire:click="$toggle('onlyAvailable')"
        >
            Solo disponibles
        </flux:button>
    </div>

    {{-- Cómo se entendió cada palabra: se puede quitar una sin borrar el resto --}}
    @if ($this->searchChips !== [])
        <div class="mb-4 flex flex-wrap items-center gap-1.5">
            @foreach ($this->searchChips as $chip)
                <button type="button" wire:key="search-chip-{{ $chip['word'] }}" wire:click="removeSearchWord(@js($chip['word']))"
                    class="inline-flex items-center gap-1 rounded-full bg-violet-100 py-1 pl-2.5 pr-1.5 text-xs text-violet-800 hover:bg-violet-200 dark:bg-violet-950/50 dark:text-violet-200">
                    <span class="text-[10px] font-semibold uppercase tracking-wide text-violet-500">{{ $chip['kind'] }}</span>
                    <span class="font-semibold">{{ $chip['word'] }}</span>
                    <flux:icon icon="x-mark" class="size-3" />
                </button>
            @endforeach
        </div>
    @endif

    <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-3">
        @forelse ($this->products as $product)
            @php
                $price = $mode === 'rental' ? ($product->rental_price_from ?? 0) : ($product->sale_price_from ?? $product->price);
                $available = $mode === 'rental' ? ($product->available_units ?? 0) : ($product->total_stock ?? 0);
            @endphp
            <article wire:key="gallery-product-{{ $product->id }}"
                class="group flex flex-col overflow-hidden rounded-2xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                <button type="button" wire:click="openDetail({{ $product->id }})"
                    class="relative block aspect-[3/4] w-full overflow-hidden bg-zinc-100 text-left dark:bg-zinc-800"
                    aria-label="Ver {{ $product->name }}">
                    @if ($product->cover_url && $product->cover_is_video)
                        {{-- Primer cuadro del video como portada (no se reproduce aquí) --}}
                        <video src="{{ $product->cover_url }}#t=0.1" muted playsinline preload="metadata"
                            class="pointer-events-none size-full object-cover"></video>
                        <span class="absolute right-2 top-2 grid size-7 place-items-center rounded-full bg-black/60 text-white">
                            <flux:icon icon="play" variant="micro" class="size-3.5" />
                        </span>
                    @elseif ($product->cover_url)
                        <img src="{{ $product->cover_url }}" alt="{{ $product->name }}" loading="lazy"
                            class="size-full object-cover transition duration-500 group-hover:scale-105" />
                    @else
                        <div class="grid size-full place-items-center">
                            <flux:icon icon="photo" class="size-8 text-zinc-300 dark:text-zinc-700" />
                        </div>
                    @endif
                    <span class="absolute left-2 top-2 rounded-full px-2 py-0.5 text-[10px] font-bold sm:left-3 sm:top-3 sm:px-2.5 sm:py-1 {{ $available > 0 ? 'bg-white/90 text-emerald-700' : 'bg-zinc-950/80 text-white' }}">
                        {{ $available > 0 ? "{$available} disp." : 'Agotado' }}
                    </span>
                    <span class="absolute bottom-2 right-2 grid size-8 place-items-center rounded-xl bg-white/90 text-zinc-700 sm:bottom-3 sm:right-3 sm:size-9">
                        <flux:icon icon="magnifying-glass-plus" class="size-4" />
                    </span>
                </button>
                <div class="flex flex-1 flex-col p-3 sm:p-4">
                    <div class="flex items-center justify-between gap-2">
                        <p class="truncate text-[10px] font-bold uppercase tracking-wider text-violet-500">
                            {{ $product->category?->name ?? 'Sin categoría' }}
                        </p>
                        @if ($product->swatch_colors->isNotEmpty())
                            <div class="flex shrink-0 -space-x-1">
                                @foreach ($product->swatch_colors->take(4) as $color)
                                    <span class="size-3.5 rounded-full border-2 border-white dark:border-zinc-900"
                                        style="background-color: {{ $color->hex_code ?? '#a1a1aa' }}" title="{{ $color->name }}"></span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <h3 class="mt-0.5 line-clamp-2 text-sm font-semibold text-zinc-900 dark:text-white">{{ $product->name }}</h3>
                    <p class="truncate text-xs text-zinc-500">{{ $product->code }}</p>
                    <div class="mt-auto flex flex-col gap-2 pt-3 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <span class="text-base font-bold text-zinc-900 sm:text-lg dark:text-white">S/ {{ number_format($price, 2) }}</span>
                            <span class="ml-1 text-xs text-zinc-500">{{ $mode === 'rental' ? '/ alquiler' : 'venta' }}</span>
                        </div>
                        <flux:button size="sm" variant="primary" icon="plus" :disabled="$available <= 0"
                            wire:click="addToCart({{ $product->id }})" class="w-full sm:w-auto">
                            Agregar
                        </flux:button>
                    </div>
                </div>
            </article>
        @empty
            <div class="col-span-full flex flex-col items-center gap-2 rounded-2xl border border-dashed border-zinc-300 py-10 text-center dark:border-zinc-700">
                <flux:icon icon="magnifying-glass" class="size-6 text-zinc-400" />
                <span class="text-sm text-zinc-500">No se encontraron productos.</span>
                @if ($this->searchChips !== [])
                    <span class="text-xs text-zinc-400">Prueba quitando alguna palabra de la búsqueda.</span>
                @endif
            </div>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $this->products->links() }}
    </div>
</div>
