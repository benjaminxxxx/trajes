<?php

namespace App\Livewire\Billing;

use App\Models\Sale;
use App\Models\SunatDocument;
use App\Services\Billing\BillingException;
use App\Services\Billing\BillingService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Vista previa y envío de una venta al servicio de facturación.
 * Pide la fecha de emisión y el tipo; el total y el detalle salen de la venta.
 */
class InvoiceSaleModal extends Component
{
    public ?int $saleId = null;

    public bool $isOpen = false;

    public string $issueDate = '';

    public string $documentType = 'boleta';

    /** Comprobante resultado del último envío (se muestra en el modal). */
    public ?int $documentId = null;

    /** @var list<string> */
    public array $apiErrors = [];

    #[On('open-invoice-sale')]
    public function open(int $id): void
    {
        $this->authorize('facturacion.emitir');
        $this->reset();
        $this->resetValidation();

        $this->saleId = $id;
        unset($this->sale, $this->document);

        $this->issueDate = now('America/Lima')->toDateString();
        $this->documentType = strtoupper((string) $this->sale->client?->document_type) === 'RUC' ? 'factura' : 'boleta';
        $this->documentId = $this->sale->liveSunatDocument?->id;
        $this->isOpen = true;
    }

    #[Computed]
    public function sale(): Sale
    {
        return Sale::with(['client', 'liveSunatDocument'])->findOrFail($this->saleId);
    }

    #[Computed]
    public function document(): ?SunatDocument
    {
        return $this->documentId ? SunatDocument::find($this->documentId) : null;
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['issueDate', 'documentType'], true)) {
            $this->apiErrors = [];
        }
    }

    public function submit(BillingService $billing): void
    {
        $this->authorize('facturacion.emitir');
        $this->apiErrors = [];

        $this->validate([
            'issueDate' => ['required', 'date'],
            'documentType' => ['required', 'in:boleta,factura'],
        ]);

        try {
            $document = $billing->invoice($this->sale, $this->documentType, $this->issueDate);
        } catch (BillingException $e) {
            $this->apiErrors = $e->messages();

            return;
        }

        $this->showResult($document);
    }

    /**
     * Consulta el estado del comprobante pendiente o reintenta el envío sin respuesta.
     */
    public function refresh(BillingService $billing): void
    {
        $this->authorize('facturacion.emitir');
        $this->apiErrors = [];

        if (! $this->document) {
            return;
        }

        try {
            $document = $billing->refresh($this->document);
        } catch (BillingException $e) {
            $this->apiErrors = $e->messages();

            return;
        }

        $this->showResult($document);
    }

    /**
     * Tras un rechazo se puede corregir (ej. el cliente en la venta) y volver a facturar.
     */
    public function startOver(): void
    {
        $this->documentId = null;
        $this->apiErrors = [];
        unset($this->document, $this->sale);
    }

    protected function showResult(SunatDocument $document): void
    {
        $this->documentId = $document->id;
        unset($this->document, $this->sale);
        $this->dispatch('saleUpdated');

        match (true) {
            $document->isValid() && $document->isTest() => Flux::toast(variant: 'success', text: "{$document->typeLabel()} {$document->fullNumber()} aceptada en modo prueba (sin valor tributario)."),
            $document->isValid() => Flux::toast(variant: 'success', text: "{$document->typeLabel()} {$document->fullNumber()} aceptada por SUNAT."),
            $document->status === SunatDocument::STATUS_REJECTED => Flux::toast(variant: 'danger', text: 'SUNAT rechazó el comprobante. Revise el motivo.'),
            default => Flux::toast(variant: 'warning', text: 'Sin respuesta de SUNAT por ahora: quedó guardado para reintentar.'),
        };
    }

    public function render(): View
    {
        $billing = app(BillingService::class);
        $ready = $this->isOpen && $this->saleId;

        return view('livewire.billing.invoice-sale-modal', [
            'settings' => $billing->settings(),
            'lines' => $ready ? $billing->lines($this->sale) : [],
            'customer' => $ready ? $billing->customer($this->sale) : null,
            'problems' => $ready && ! $this->document && $this->issueDate
                ? $billing->problems($this->sale, $this->documentType, $this->issueDate) : [],
        ]);
    }
}
