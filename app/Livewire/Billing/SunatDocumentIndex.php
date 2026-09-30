<?php

namespace App\Livewire\Billing;

use App\Models\BillingSetting;
use App\Models\SunatDocument;
use App\Services\Billing\BillingException;
use App\Services\Billing\BillingService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Comprobantes electrónicos emitidos desde las ventas: estado ante SUNAT, PDF,
 * reintentos de los que quedaron sin respuesta y ventas anuladas que requieren nota de crédito.
 */
#[Title('Comprobantes electrónicos')]
class SunatDocumentIndex extends Component
{
    use WithPagination;

    #[Url(history: true)]
    public string $search = '';

    /** todos | pendientes | rechazados | nota_credito */
    #[Url(history: true)]
    public string $filter = 'todos';

    public function mount(): void
    {
        $this->authorize('facturacion.emitir');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilter(): void
    {
        $this->resetPage();
    }

    public function refresh(int $id, BillingService $billing): void
    {
        $this->authorize('facturacion.emitir');

        $document = SunatDocument::findOrFail($id);

        try {
            $document = $billing->refresh($document);
        } catch (BillingException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        Flux::toast(
            variant: $document->isValid() ? 'success' : ($document->isAwaiting() ? 'warning' : 'danger'),
            text: "{$document->fullNumber()}: {$document->statusLabel()}",
        );
    }

    /**
     * Reintenta todos los que siguen sin respuesta definitiva.
     */
    public function refreshAll(BillingService $billing): void
    {
        $this->authorize('facturacion.emitir');

        $done = 0;
        foreach (SunatDocument::whereIn('status', [SunatDocument::STATUS_PENDING, SunatDocument::STATUS_SEND_ERROR, SunatDocument::STATUS_OFFLINE])->orderBy('id')->limit(30)->get() as $document) {
            try {
                $billing->refresh($document);
                $done++;
            } catch (BillingException $e) {
                Flux::toast(variant: 'danger', text: $e->getMessage());

                return;
            }
        }

        Flux::toast(text: "Se consultaron {$done} comprobantes.");
    }

    public function render(): View
    {
        $documents = SunatDocument::query()
            ->with(['sale:id,code,status', 'user:id,name'])
            ->when($this->filter === 'pendientes', fn ($q) => $q->whereIn('status', [SunatDocument::STATUS_PENDING, SunatDocument::STATUS_SEND_ERROR, SunatDocument::STATUS_OFFLINE]))
            ->when($this->filter === 'rechazados', fn ($q) => $q->where('status', SunatDocument::STATUS_REJECTED))
            ->when($this->filter === 'nota_credito', fn ($q) => $q->where('credit_note_required', true))
            ->when(filled($this->search), function ($q) {
                $search = trim($this->search);
                $term = "%{$search}%";

                // "F001-12" → serie + número (sin concatenar en SQL: MySQL y SQLite difieren).
                if (preg_match('/^([A-Za-z0-9]{4})-?(\d+)$/', $search, $m)) {
                    $q->where('series', strtoupper($m[1]))->where('number', (int) $m[2]);

                    return;
                }

                $q->where(fn ($q) => $q->where('customer_name', 'like', $term)
                    ->orWhere('customer_document_number', 'like', $term)
                    ->orWhere('series', 'like', $term)
                    ->orWhereHas('sale', fn ($s) => $s->where('code', 'like', $term)));
            })
            ->latest('id')
            ->paginate(15);

        return view('livewire.billing.sunat-document-index', [
            'documents' => $documents,
            'settings' => BillingSetting::current(),
            'awaitingCount' => SunatDocument::whereIn('status', [SunatDocument::STATUS_PENDING, SunatDocument::STATUS_SEND_ERROR, SunatDocument::STATUS_OFFLINE])->count(),
            'creditNoteCount' => SunatDocument::where('credit_note_required', true)->count(),
        ]);
    }
}
