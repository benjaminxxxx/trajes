<x-layouts::app :title="$title.' '.$operation->code">
    <div class="flex flex-col gap-5"
        x-data="{
            config: @js([
            'pdfUrl' => $pdfUrl,
            'fileName' => $fileName,
            'title' => $title.' '.$operation->code,
            'text' => $shareText,
            'whatsappWebUrl' => $whatsappWebUrl,
            'whatsappMobileUrl' => $whatsappMobileUrl,
        ]),
            sharing: false,
            error: null,
            async share() {
                this.error = null;

                // Web (con mouse): WhatsApp Web con el mensaje y el enlace al PDF.
                if (! window.matchMedia('(pointer: coarse)').matches) {
                    window.open(this.config.whatsappWebUrl, '_blank', 'noopener');
                    return;
                }

                // Celular/tablet: se comparte el archivo PDF con la hoja nativa (WhatsApp, etc.).
                this.sharing = true;
                try {
                    const response = await fetch(this.config.pdfUrl, { credentials: 'same-origin' });
                    const file = new File([await response.blob()], this.config.fileName, { type: 'application/pdf' });

                    if (navigator.canShare && navigator.canShare({ files: [file] })) {
                        await navigator.share({ files: [file], title: this.config.title, text: this.config.text });
                    } else {
                        window.location.href = this.config.whatsappMobileUrl;
                    }
                } catch (e) {
                    if (e.name !== 'AbortError') {
                        this.error = 'No se pudo compartir el archivo; se abrirá WhatsApp con el enlace.';
                        window.location.href = this.config.whatsappMobileUrl;
                    }
                } finally {
                    this.sharing = false;
                }
            },
        }">

        <!-- Encabezado y acciones -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div class="space-y-3">
                <flux:breadcrumbs>
                    <flux:breadcrumbs.item :href="route('dashboard')" icon="home" />
                    <flux:breadcrumbs.item :href="$backUrl">{{ $type === 'venta' ? 'Ventas' : 'Alquileres' }}</flux:breadcrumbs.item>
                    <flux:breadcrumbs.item>{{ $operation->code }}</flux:breadcrumbs.item>
                </flux:breadcrumbs>
                <div class="flex items-center gap-2">
                    <flux:heading size="xl">{{ $title }} {{ $operation->code }}</flux:heading>
                    @if ($operation->isCancelled())
                        <flux:badge color="red">Anulado</flux:badge>
                    @endif
                </div>
                <flux:text>
                    {{ $operation->client?->display_name ?? 'Cliente varios' }}
                    @if ($operation->client?->mobile) · {{ $operation->client->mobile }} @endif
                </flux:text>
            </div>

            <div class="flex flex-wrap gap-2">
                <flux:button variant="primary" icon="share" x-on:click="share()" x-bind:disabled="sharing">
                    <span x-show="! sharing">Compartir</span>
                    <span x-show="sharing" x-cloak>Preparando...</span>
                </flux:button>
                <flux:button icon="arrow-down-tray" :href="$downloadUrl">Descargar</flux:button>
                <flux:button variant="subtle" icon="plus" :href="$newUrl" wire:navigate>{{ $newLabel }}</flux:button>
            </div>
        </div>

        <p x-show="error" x-text="error" x-cloak class="text-sm text-red-600 dark:text-red-400"></p>

        <!-- Visor del PDF -->
        <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-zinc-100 dark:border-zinc-800 dark:bg-zinc-900">
            <object data="{{ $pdfUrl }}" type="application/pdf" class="h-[75vh] w-full">
                {{-- Muchos navegadores de celular no muestran PDF incrustados --}}
                <div class="flex flex-col items-center gap-3 p-10 text-center">
                    <flux:icon icon="document-text" class="size-10 text-zinc-400" />
                    <flux:text>Tu navegador no puede mostrar el PDF aquí.</flux:text>
                    <flux:button :href="$pdfUrl" target="_blank" icon="arrow-top-right-on-square">Abrir comprobante</flux:button>
                </div>
            </object>
        </div>
    </div>

</x-layouts::app>
