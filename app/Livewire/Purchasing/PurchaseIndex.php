<?php

namespace App\Livewire\Purchasing;

use App\Models\Purchase;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class PurchaseIndex extends Component
{
    use WithPagination;

    #[Url(history: true)]
    public string $search = '';

    #[Url(history: true)]
    public bool $onlyPendingInvoice = false;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingOnlyPendingInvoice(): void
    {
        $this->resetPage();
    }

    #[On('purchaseUpdated')]
    public function refreshList(): void
    {
        //
    }

    /**
     * Un borrador se retoma en el formulario; una compra confirmada se revisa en su detalle.
     */
    public function openPurchase(int $id): void
    {
        if (Purchase::find($id)?->isDraft()) {
            $this->redirectRoute('purchasing.purchases.create', ['borrador' => $id], navigate: true);

            return;
        }

        $this->dispatch('open-purchase-detail', id: $id);
    }

    public function render(): View
    {
        $purchases = Purchase::query()
            ->with(['supplier:id,display_name,document_number', 'user:id,name'])
            ->withSum('details as units_count', 'quantity')
            ->when($this->onlyPendingInvoice, fn ($q) => $q->where('has_invoice_pending', true))
            ->when(filled($this->search), function ($q) {
                $term = '%'.trim($this->search).'%';
                $purchaseNumber = ltrim(trim($this->search), '#');

                $q->where(function ($q) use ($term, $purchaseNumber) {
                    $q->where('document_number', 'like', $term)
                        ->when(ctype_digit($purchaseNumber), fn ($q) => $q->orWhere('id', (int) $purchaseNumber))
                        ->orWhereHas('supplier', fn ($s) => $s->where('display_name', 'like', $term)
                            ->orWhere('document_number', 'like', $term));
                });
            })
            ->latest('issue_date')
            ->latest('id')
            ->paginate(15);

        return view('livewire.purchasing.purchase-index', [
            'purchases' => $purchases,
            'pendingInvoiceCount' => Purchase::where('has_invoice_pending', true)->count(),
        ]);
    }
}
