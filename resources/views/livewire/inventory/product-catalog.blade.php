<div class="space-y-4">
    <div>
        <flux:heading size="xl" class="text-zinc-900 dark:text-white">Catálogo</flux:heading>
        <flux:subheading class="text-zinc-500 dark:text-zinc-400">
            Toca una prenda para verla en grande y compartirla por WhatsApp.
        </flux:subheading>
    </div>

    {{-- Filtros: búsqueda, tipo (hombre, mujer...) y categoría --}}
    <div class="sticky top-0 z-10 -mx-4 space-y-3 bg-white/95 px-4 py-3 backdrop-blur sm:mx-0 sm:rounded-2xl sm:px-0 dark:bg-zinc-900/95">
        <div class="flex flex-col gap-3 sm:flex-row">
            <div class="flex-1">
                <flux:input wire:model.live.debounce.400ms="search" placeholder="Busca: rojo L satín quinceañera..."
                    icon="magnifying-glass" clearable />
            </div>
            <flux:select wire:model.live="categoryId" class="sm:max-w-60">
                <flux:select.option value="">Todas las categorías</flux:select.option>
                @foreach ($this->categories as $category)
                    <flux:select.option wire:key="catalog-category-{{ $category->id }}" value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <div class="-mx-4 flex gap-2 overflow-x-auto px-4 [scrollbar-width:none] sm:mx-0 sm:px-0">
            <button type="button" wire:click="selectGender('')"
                class="shrink-0 rounded-full px-4 py-1.5 text-sm font-semibold transition {{ $gender === '' ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'bg-zinc-100 text-zinc-700 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-200' }}">
                Todos
            </button>
            @foreach ($genders as $value => $label)
                <button type="button" wire:key="catalog-gender-{{ $value }}" wire:click="selectGender('{{ $value }}')"
                    class="shrink-0 rounded-full px-4 py-1.5 text-sm font-semibold transition {{ $gender === $value ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'bg-zinc-100 text-zinc-700 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-200' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </div>

    {{-- Grilla tipo catálogo impreso: foto grande, nombre y precio --}}
    <div class="grid grid-cols-2 gap-x-3 gap-y-5 sm:grid-cols-3 sm:gap-x-4 lg:grid-cols-4 2xl:grid-cols-5" wire:loading.class="opacity-60">
        @forelse ($this->products->take($limit) as $product)
            <button type="button" wire:key="catalog-product-{{ $product->id }}" wire:click="openDetail({{ $product->id }})"
                class="group flex flex-col text-left" aria-label="Ver {{ $product->name }}">
                <div class="relative aspect-[3/4] w-full overflow-hidden rounded-xl bg-zinc-100 dark:bg-zinc-800">
                    @if ($product->cover_url && $product->cover_is_video)
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
                </div>
                <h3 class="mt-2 line-clamp-2 text-sm font-medium leading-snug text-zinc-900 dark:text-white">{{ $product->name }}</h3>
                @if ($product->list_price !== null)
                    <p class="mt-0.5 text-sm font-bold text-zinc-900 dark:text-white">
                        S/ {{ number_format($product->list_price, 2) }}
                        @if ($product->list_price_label)
                            <span class="text-xs font-normal text-zinc-500">{{ $product->list_price_label }}</span>
                        @endif
                    </p>
                @endif
            </button>
        @empty
            <div class="col-span-full flex flex-col items-center gap-2 rounded-2xl border border-dashed border-zinc-300 py-12 text-center dark:border-zinc-700">
                <flux:icon icon="magnifying-glass" class="size-6 text-zinc-400" />
                <span class="text-sm text-zinc-500">No hay prendas con esos filtros.</span>
            </div>
        @endforelse
    </div>

    @if ($this->hasMore)
        {{-- Carga más al llegar al final, como al hojear un PDF --}}
        <div wire:intersect="loadMore" class="flex justify-center py-4">
            <flux:button variant="ghost" wire:click="loadMore" icon="arrow-down">Ver más</flux:button>
        </div>
    @endif

    <livewire:shared.product-detail-modal key="catalog-product-detail" />
</div>
