<div>
    <div class="mb-4 flex flex-col gap-3 sm:flex-row">
        <div class="relative flex-1">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Buscar por producto o código"
                icon="magnifying-glass" />
        </div>
        <flux:button
            :variant="$onlyAvailable ? 'primary' : 'outline'"
            icon="check"
            wire:click="$toggle('onlyAvailable')"
        >
            Solo disponibles
        </flux:button>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($this->products as $product)
            @php
                $price = $mode === 'rental' ? ($product->rental_price_from ?? 0) : ($product->sale_price_from ?? $product->price);
                $available = $mode === 'rental' ? ($product->available_units ?? 0) : ($product->total_stock ?? 0);
            @endphp
            <article wire:key="gallery-product-{{ $product->id }}"
                class="group overflow-hidden rounded-2xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                <button type="button" wire:click="openDetail({{ $product->id }})"
                    class="relative block aspect-[4/3] w-full overflow-hidden bg-zinc-100 text-left dark:bg-zinc-800">
                    @if ($product->cover_url)
                        <img src="{{ $product->cover_url }}" alt="{{ $product->name }}"
                            class="size-full object-cover transition duration-500 group-hover:scale-105" />
                    @else
                        <div class="grid size-full place-items-center">
                            <flux:icon icon="photo" class="size-8 text-zinc-300 dark:text-zinc-700" />
                        </div>
                    @endif
                    <span class="absolute left-3 top-3 rounded-full px-2.5 py-1 text-[10px] font-bold {{ $available > 0 ? 'bg-white/90 text-emerald-700' : 'bg-zinc-950/80 text-white' }}">
                        {{ $available > 0 ? "{$available} disponibles" : 'No disponible' }}
                    </span>
                    <span class="absolute bottom-3 right-3 grid size-9 place-items-center rounded-xl bg-white/90 text-zinc-700" title="Ver detalle">
                        <flux:icon icon="magnifying-glass-plus" class="size-4" />
                    </span>
                </button>
                <div class="p-4">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-violet-500">
                            {{ $product->category?->name ?? 'Sin categoría' }}
                        </p>
                        @if ($product->swatch_colors->isNotEmpty())
                            <div class="flex -space-x-1">
                                @foreach ($product->swatch_colors->take(4) as $color)
                                    <span class="size-3.5 rounded-full border-2 border-white dark:border-zinc-900"
                                        style="background-color: {{ $color->hex_code ?? '#a1a1aa' }}" title="{{ $color->name }}"></span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <h3 class="mt-0.5 text-sm font-semibold text-zinc-900 dark:text-white">{{ $product->name }}</h3>
                    <p class="text-xs text-zinc-500">{{ $product->code }}</p>
                    <div class="mt-4 flex items-end justify-between">
                        <div>
                            <span class="text-lg font-bold text-zinc-900 dark:text-white">S/ {{ number_format($price, 2) }}</span>
                            <span class="ml-1 text-xs text-zinc-500">{{ $mode === 'rental' ? '/ alquiler' : 'venta' }}</span>
                        </div>
                        <flux:button size="sm" variant="primary" icon="plus" :disabled="$available <= 0"
                            wire:click="addToCart({{ $product->id }})">
                            Agregar
                        </flux:button>
                    </div>
                </div>
            </article>
        @empty
            <div class="col-span-full flex flex-col items-center gap-2 rounded-2xl border border-dashed border-zinc-300 py-10 text-center dark:border-zinc-700">
                <flux:icon icon="magnifying-glass" class="size-6 text-zinc-400" />
                <span class="text-sm text-zinc-500">No se encontraron productos.</span>
            </div>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $this->products->links() }}
    </div>
</div>