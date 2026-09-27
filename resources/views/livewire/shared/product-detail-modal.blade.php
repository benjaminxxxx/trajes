<flux:modal wire:model="show" class="max-w-3xl">
    @if ($this->product)
        <div
            x-data="{
                media: @js($this->mediaPayload),
                activeColor: null,
                index: 0,
                get visible() {
                    return this.activeColor ? this.media.filter(m => m.color_id === this.activeColor) : this.media;
                },
                get current() { return this.visible[this.index] ?? this.visible[0] ?? null; },
                next() { this.index = (this.index + 1) % (this.visible.length || 1); },
                prev() { this.index = (this.index - 1 + this.visible.length) % (this.visible.length || 1); },
            }"
            x-init="$watch('activeColor', () => index = 0)"
            class="grid gap-6 sm:grid-cols-[1.1fr_.9fr]"
        >
            {{-- Slider --}}
            <div>
                <div class="relative aspect-[4/3] w-full overflow-hidden rounded-2xl bg-zinc-100 dark:bg-zinc-800">
                    <template x-if="current && current.file_type === 'video'">
                        <video :src="current.url" controls class="size-full object-cover"></video>
                    </template>
                    <template x-if="!current || current.file_type !== 'video'">
                        <img :src="current?.url" class="size-full object-cover" :alt="'{{ $this->product->name }}'" x-show="current" />
                    </template>
                    <div x-show="!current" class="grid size-full place-items-center">
                        <flux:icon icon="photo" class="size-10 text-zinc-300 dark:text-zinc-600" />
                    </div>

                    <template x-if="visible.length > 1">
                        <div>
                            <button @click="prev" type="button"
                                class="absolute left-2 top-1/2 grid size-8 -translate-y-1/2 place-items-center rounded-full bg-white/90 text-zinc-700 shadow">
                                <flux:icon icon="chevron-left" class="size-4" />
                            </button>
                            <button @click="next" type="button"
                                class="absolute right-2 top-1/2 grid size-8 -translate-y-1/2 place-items-center rounded-full bg-white/90 text-zinc-700 shadow">
                                <flux:icon icon="chevron-right" class="size-4" />
                            </button>
                        </div>
                    </template>
                </div>

                {{-- Puntos del slider --}}
                <div class="mt-2 flex justify-center gap-1.5" x-show="visible.length > 1">
                    <template x-for="(item, i) in visible" :key="i">
                        <button @click="index = i" type="button" class="size-2 rounded-full"
                            :class="i === index ? 'bg-violet-600' : 'bg-zinc-300 dark:bg-zinc-600'"></button>
                    </template>
                </div>

                {{-- Swatches de color: filtran el slider a las fotos de ese color --}}
                @if ($this->colors->isNotEmpty())
                    <div class="mt-4 flex flex-wrap items-center gap-2">
                        <button @click="activeColor = null" type="button"
                            class="rounded-full border px-2.5 py-1 text-[11px] font-semibold"
                            :class="!activeColor ? 'border-violet-500 text-violet-600' : 'border-zinc-200 text-zinc-500 dark:border-zinc-700'">
                            Todos
                        </button>
                        @foreach ($this->colors as $color)
                            <button @click="activeColor = {{ $color->id }}" type="button" title="{{ $color->name }}"
                                class="size-7 rounded-full border-2"
                                :class="activeColor === {{ $color->id }} ? 'border-violet-600' : 'border-white dark:border-zinc-900'"
                                style="background-color: {{ $color->hex_code ?? '#a1a1aa' }}"
                            ></button>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Info + stock por variante --}}
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-violet-500">
                    {{ $this->product->category?->name ?? 'Sin categoría' }} · {{ $this->product->code }}
                </p>
                <h2 class="mt-2 text-xl font-semibold text-zinc-900 dark:text-white">{{ $this->product->name }}</h2>
                @if ($this->product->description)
                    <p class="mt-2 text-sm leading-6 text-zinc-500 dark:text-zinc-400">{{ $this->product->description }}</p>
                @endif

                <p class="mt-5 mb-2 text-xs font-semibold uppercase tracking-wide text-zinc-500">
                    {{ $mode === 'rental' ? 'Disponibilidad por talla y color' : 'Stock por talla y color' }}
                </p>

                @if ($this->variants->isEmpty())
                    <p class="text-sm text-zinc-500">Este producto no tiene variantes registradas.</p>
                @else
                    <div class="flex flex-col divide-y divide-zinc-100 rounded-xl border border-zinc-200 dark:divide-zinc-800 dark:border-zinc-800">
                        @foreach ($this->variants as $variant)
                            @php
                                $qty = $mode === 'rental' ? ($variant->available_units ?? 0) : $variant->stock;
                            @endphp
                            <div class="flex items-center justify-between gap-3 px-3 py-2 text-sm">
                                <span class="flex items-center gap-2">
                                    @if ($variant->color)
                                        <span class="size-3 rounded-full border border-zinc-200" style="background-color: {{ $variant->color->hex_code ?? '#a1a1aa' }}"></span>
                                    @endif
                                    <span class="font-medium text-zinc-800 dark:text-zinc-200">
                                        {{ $variant->size->name ?? 'Talla única' }}{{ $variant->color ? ' · '.$variant->color->name : '' }}
                                    </span>
                                </span>
                                <span class="rounded-full px-2 py-0.5 text-xs font-bold {{ $qty > 0 ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300' : 'bg-zinc-100 text-zinc-500 dark:bg-zinc-800' }}">
                                    {{ $qty }} {{ $mode === 'rental' ? 'disponibles' : 'en stock' }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @endif
</flux:modal>