<div class="flex flex-col gap-5">
    <!-- Header + buscador -->
    <div
        class="rounded-[2rem] border border-white/80 bg-white/70 p-5 shadow-xl shadow-slate-900/[0.04] backdrop-blur-xl sm:p-8 dark:border-slate-800 dark:bg-slate-900/75">
        <div class="space-y-4">
            <flux:breadcrumbs>
                <flux:breadcrumbs.item :href="route('dashboard')" icon="home" />
                <flux:breadcrumbs.item>Inventario</flux:breadcrumbs.item>
                <flux:breadcrumbs.item :href="route('inventory.physical-items.index')">Seguimiento de prendas</flux:breadcrumbs.item>
            </flux:breadcrumbs>

            <div>
                <flux:heading size="xl">Seguimiento de prendas</flux:heading>
                <flux:text class="mt-1.5">
                    Busca un producto para ver sus estadísticas del año, o el código de una prenda física para ver toda su historia.
                </flux:text>
            </div>
        </div>

        <div class="relative mt-6">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                placeholder="Nombre o código de producto, o código de prenda (ej. TRN-BAR-001-1-00003)..." />

            @if (mb_strlen(trim($search)) >= 2)
                @php($results = $this->results)
                <div class="absolute z-20 mt-1 w-full rounded-xl border border-zinc-200 bg-white p-2 shadow-xl dark:border-zinc-700 dark:bg-zinc-900">
                    @if ($results['products']->isEmpty() && $results['units']->isEmpty())
                        <p class="px-3 py-2 text-sm text-zinc-500">Sin resultados.</p>
                    @endif

                    @if ($results['products']->isNotEmpty())
                        <p class="px-2 pb-1 pt-1 text-[11px] font-semibold uppercase tracking-wide text-zinc-400">Productos</p>
                        @foreach ($results['products'] as $product)
                            <button type="button" wire:key="result-product-{{ $product->id }}" wire:click="selectProduct({{ $product->id }})"
                                class="flex w-full items-center gap-3 rounded-lg p-2 text-left hover:bg-zinc-50 dark:hover:bg-zinc-800">
                                <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-violet-100 dark:bg-violet-950/60">
                                    <flux:icon icon="tag" class="size-4 text-violet-600 dark:text-violet-400" />
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $product->name }}</span>
                                    <span class="block text-xs text-zinc-500">{{ $product->code }} · {{ $product->category?->name ?? 'Sin categoría' }}</span>
                                </span>
                                <flux:badge size="sm" :color="$product->allow_rental ? 'violet' : 'zinc'">
                                    {{ $product->allow_rental ? 'Con prendas físicas' : 'Solo venta' }}
                                </flux:badge>
                            </button>
                        @endforeach
                    @endif

                    @if ($results['units']->isNotEmpty())
                        <p class="px-2 pb-1 pt-2 text-[11px] font-semibold uppercase tracking-wide text-zinc-400">Prendas físicas</p>
                        @foreach ($results['units'] as $unit)
                            <button type="button" wire:key="result-unit-{{ $unit->id }}" wire:click="selectUnit({{ $unit->id }})"
                                class="flex w-full items-center gap-3 rounded-lg p-2 text-left hover:bg-zinc-50 dark:hover:bg-zinc-800">
                                <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-sky-100 dark:bg-sky-950/60">
                                    <flux:icon icon="qr-code" class="size-4 text-sky-600 dark:text-sky-400" />
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate font-mono text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $unit->code }}</span>
                                    <span class="block text-xs text-zinc-500">{{ $unit->variant?->product?->name }} · {{ $unit->variant?->label() }}</span>
                                </span>
                                <flux:badge size="sm" :color="\App\Models\PhysicalProduct::STATUS_COLORS[$unit->status] ?? 'zinc'">
                                    {{ \App\Models\PhysicalProduct::STATUS_LABELS[$unit->status] ?? $unit->status }}
                                </flux:badge>
                            </button>
                        @endforeach
                    @endif
                </div>
            @endif
        </div>
    </div>

    @if ($history = $this->unitHistory)
        @include('livewire.inventory.partials.unit-history', ['history' => $history])
    @elseif ($this->product && ($data = $this->productStats))
        @include('livewire.inventory.partials.product-stats', ['product' => $this->product, 'data' => $data])
    @else
        <div class="rounded-2xl border border-dashed border-zinc-300 p-12 text-center dark:border-zinc-700">
            <flux:icon icon="magnifying-glass" class="mx-auto size-8 text-zinc-400" />
            <p class="mt-2 text-sm text-zinc-500">Busca un producto o una prenda para empezar.</p>
        </div>
    @endif
</div>

@script
<script>
    Alpine.data('trackerMonthlyChart', (labels, sold, rented) => ({
        chart: null,

        init() {
            if (! window.Chart) {
                return;
            }

            const datasets = [{ label: 'Vendidas', data: sold, backgroundColor: 'rgba(16, 185, 129, 0.75)', borderRadius: 6 }];

            if (rented) {
                datasets.push({ label: 'Alquiladas', data: rented, backgroundColor: 'rgba(124, 58, 237, 0.75)', borderRadius: 6 });
            }

            this.chart = new window.Chart(this.$refs.canvas, {
                type: 'bar',
                data: { labels, datasets },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom', display: datasets.length > 1 } },
                    scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
                },
            });
        },

        destroy() {
            this.chart?.destroy();
        },
    }));
</script>
@endscript
