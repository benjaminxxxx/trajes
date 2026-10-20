<div>
    {{-- Visor tipo reel. Todo lo maneja Alpine (wire:ignore): Livewire solo manda los datos con `product-reel-open`. --}}
    <div wire:ignore
        x-data="{
            open: false,
            p: null,
            index: 0,
            muted: true,
            showStock: true,
            observer: null,
            show(product) {
                this.p = product;
                this.index = 0;
                this.showStock = true;
                this.open = true;
                document.body.style.overflow = 'hidden';
                // En el celular, el botón Atrás cierra el visor en vez de salir de la venta.
                history.pushState({ productReel: true }, '');
                this.$nextTick(() => this.observe());
            },
            observe() {
                this.observer?.disconnect();
                const root = this.$refs.scroller;
                if (! root) return;
                root.scrollTop = 0;
                this.observer = new IntersectionObserver((entries) => entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        this.index = Number(entry.target.dataset.index);
                        this.playCurrent();
                    }
                }), { root, threshold: 0.6 });
                root.querySelectorAll('[data-slide]').forEach((slide) => this.observer.observe(slide));
            },
            videos() {
                return this.$refs.scroller ? [...this.$refs.scroller.querySelectorAll('video')] : [];
            },
            playCurrent() {
                this.videos().forEach((video) => {
                    if (Number(video.dataset.index) === this.index) {
                        video.muted = this.muted;
                        video.play().catch(() => {});
                    } else {
                        video.pause();
                    }
                });
            },
            toggleSound() {
                this.muted = ! this.muted;
                this.videos().forEach((video) => video.muted = this.muted);
            },
            togglePlay(video) {
                video.paused ? video.play().catch(() => {}) : video.pause();
            },
            // Detecta si el medio es vertical u horizontal para dimensionarlo sin recortarlo.
            orient(item, width, height) {
                item.vertical = height > width;
            },
            go(step) {
                const slides = this.$refs.scroller?.querySelectorAll('[data-slide]') ?? [];
                slides[this.index + step]?.scrollIntoView({ behavior: 'smooth' });
            },
            addToCart() {
                Livewire.dispatch('product-picker-requested', { context: this.p.context, productId: this.p.id, mode: this.p.mode });
                this.close();
            },
            close(fromHistory = false) {
                if (! this.open) return;
                // Pausar y soltar los videos: al cerrar no debe seguir sonando ni descargando.
                this.videos().forEach((video) => { video.pause(); video.removeAttribute('src'); video.load(); });
                this.observer?.disconnect();
                this.open = false;
                this.p = null;
                document.body.style.overflow = '';
                if (! fromHistory && history.state?.productReel) history.back();
            },
        }"
        x-on:product-reel-open.window="show($event.detail.product)"
        x-on:popstate.window="close(true)"
        x-on:keydown.escape.window="close()"
        x-on:keydown.arrow-down.window="open && go(1)"
        x-on:keydown.arrow-up.window="open && go(-1)">
        <template x-teleport="body">
            <div x-show="open" x-cloak x-transition.opacity.duration.150ms
                class="fixed inset-0 z-[100] bg-black text-white" role="dialog" aria-modal="true" :aria-label="p?.name">
                <template x-if="p">
                    <div class="relative h-full w-full">
                        {{-- Diapositivas: una por foto/video, se deslizan hacia arriba como un reel --}}
                        <div x-ref="scroller" class="h-full w-full snap-y snap-mandatory overflow-y-auto overscroll-contain [scrollbar-width:none]">
                            <template x-for="(item, i) in p.media" :key="item.url">
                                <section data-slide :data-index="i" class="relative flex h-[100dvh] w-full snap-start snap-always items-center justify-center">
                                    <template x-if="item.type === 'video'">
                                        <video :src="item.url" :data-index="i" loop playsinline muted preload="metadata"
                                            x-on:loadedmetadata="orient(item, $el.videoWidth, $el.videoHeight)"
                                            x-on:click="togglePlay($el)"
                                            :class="item.vertical === false ? 'h-auto w-full' : 'h-full w-auto'"
                                            class="max-h-full max-w-full object-contain"></video>
                                    </template>
                                    <template x-if="item.type !== 'video'">
                                        <img :src="item.url" :alt="p.name" x-on:load="orient(item, $el.naturalWidth, $el.naturalHeight)"
                                            :class="item.vertical === false ? 'h-auto w-full' : 'h-full w-auto'"
                                            class="max-h-full max-w-full object-contain" />
                                    </template>
                                </section>
                            </template>

                            <template x-if="p.media.length === 0">
                                <section data-slide data-index="0" class="flex h-[100dvh] w-full items-center justify-center">
                                    <flux:icon icon="photo" class="size-16 text-white/20" />
                                </section>
                            </template>
                        </div>

                        {{-- Barra superior: posición, sonido y cerrar --}}
                        <div class="pointer-events-none absolute inset-x-0 top-0 flex items-start justify-between gap-3 bg-gradient-to-b from-black/60 to-transparent px-4 pb-10 pt-[calc(env(safe-area-inset-top,0px)+0.75rem)]">
                            <span class="pointer-events-auto rounded-full bg-black/50 px-2.5 py-1 text-xs font-semibold" x-show="p.media.length > 1"
                                x-text="`${index + 1} / ${p.media.length}`"></span>
                            <span x-show="p.media.length <= 1"></span>
                            <div class="pointer-events-auto flex gap-2">
                                <button type="button" x-show="p.media.some((item) => item.type === 'video')" x-on:click="toggleSound()"
                                    class="grid size-10 place-items-center rounded-full bg-black/50 backdrop-blur hover:bg-black/70"
                                    :aria-label="muted ? 'Activar sonido' : 'Silenciar'">
                                    <flux:icon icon="speaker-x-mark" class="size-5" x-show="muted" />
                                    <flux:icon icon="speaker-wave" class="size-5" x-show="! muted" />
                                </button>
                                <button type="button" x-on:click="close()" class="grid size-10 place-items-center rounded-full bg-black/50 backdrop-blur hover:bg-black/70"
                                    aria-label="Cerrar">
                                    <flux:icon icon="x-mark" class="size-6" />
                                </button>
                            </div>
                        </div>

                        {{-- Stock por talla/color flotando en una esquina; si no hay, no aparece nada --}}
                        <template x-if="p.variants.length > 0">
                            <div class="absolute left-4 top-[calc(env(safe-area-inset-top,0px)+3.75rem)] max-w-[60%]">
                                <button type="button" x-on:click="showStock = ! showStock"
                                    class="flex items-center gap-1.5 rounded-full bg-black/55 px-3 py-1.5 text-xs font-semibold backdrop-blur">
                                    <span class="size-2 rounded-full bg-emerald-400"></span>
                                    <span x-text="`${p.available} disponibles`"></span>
                                    <flux:icon icon="chevron-down" class="size-3.5 transition" ::class="showStock && 'rotate-180'" />
                                </button>
                                <div x-show="showStock" x-transition class="mt-2 flex max-h-[40dvh] flex-col gap-1 overflow-y-auto rounded-2xl bg-black/55 p-2 backdrop-blur">
                                    <template x-for="variant in p.variants" :key="variant.label">
                                        <div class="flex items-center justify-between gap-3 px-1.5 py-0.5 text-xs">
                                            <span class="flex items-center gap-1.5">
                                                <span x-show="variant.hex" class="size-2.5 rounded-full ring-1 ring-white/40" :style="`background-color: ${variant.hex}`"></span>
                                                <span x-text="variant.label"></span>
                                            </span>
                                            <span class="font-bold tabular-nums" x-text="variant.qty"></span>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>

                        {{-- Flechas en escritorio (en el celular se desliza) --}}
                        <div class="absolute right-4 top-1/2 hidden -translate-y-1/2 flex-col gap-2 sm:flex" x-show="p.media.length > 1">
                            <button type="button" x-on:click="go(-1)" :disabled="index === 0" aria-label="Anterior"
                                class="grid size-10 place-items-center rounded-full bg-white/15 backdrop-blur hover:bg-white/25 disabled:opacity-30">
                                <flux:icon icon="chevron-up" class="size-5" />
                            </button>
                            <button type="button" x-on:click="go(1)" :disabled="index === p.media.length - 1" aria-label="Siguiente"
                                class="grid size-10 place-items-center rounded-full bg-white/15 backdrop-blur hover:bg-white/25 disabled:opacity-30">
                                <flux:icon icon="chevron-down" class="size-5" />
                            </button>
                        </div>

                        {{-- Información del producto sobre el video --}}
                        <div class="pointer-events-none absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/85 via-black/40 to-transparent px-4 pb-[calc(env(safe-area-inset-bottom,0px)+1rem)] pt-16">
                            <div class="pointer-events-auto mx-auto flex max-w-xl items-end justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-[11px] font-bold uppercase tracking-wider text-violet-300">
                                        <span x-text="p.category ?? ''"></span>
                                        <span x-show="p.category">·</span>
                                        <span x-text="p.code"></span>
                                    </p>
                                    <h2 class="mt-0.5 text-lg font-semibold leading-tight" x-text="p.name"></h2>
                                    <div class="mt-1.5 flex flex-wrap gap-1" x-show="p.highlights.length">
                                        <template x-for="highlight in p.highlights" :key="highlight">
                                            <span class="rounded-full bg-white/15 px-2 py-0.5 text-[11px]" x-text="highlight"></span>
                                        </template>
                                    </div>
                                    <p class="mt-2 line-clamp-2 text-xs text-white/70" x-show="p.description" x-text="p.description"></p>
                                </div>
                                <div class="flex shrink-0 flex-col items-end gap-2">
                                    <p x-show="p.price !== null" class="text-right">
                                        <span class="block text-xl font-bold" x-text="`S/ ${Number(p.price).toFixed(2)}`"></span>
                                        <span class="text-[11px] text-white/60" x-text="p.priceLabel"></span>
                                    </p>
                                    <button type="button" x-show="p.context && p.available > 0" x-on:click="addToCart()"
                                        class="flex items-center gap-1 rounded-full bg-violet-600 px-4 py-2 text-sm font-semibold hover:bg-violet-500">
                                        <flux:icon icon="plus" class="size-4" /> Agregar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </template>
    </div>
</div>
