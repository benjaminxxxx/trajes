<?php

namespace App\Livewire\Sale;

use App\Models\Sale;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class SaleIndex extends Component
{
    use WithPagination;

    #[Url(history: true)]
    public string $search = '';

    #[Url(history: true)]
    public string $status = 'all'; // all, completed, cancelled

    #[Url(history: true)]
    public bool $onlyPendingInvoice = false;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function updatingOnlyPendingInvoice(): void
    {
        $this->resetPage();
    }

    #[On('saleUpdated')]
    public function refreshList(): void
    {
        //
    }

    public function openSale(int $id): void
    {
        $this->dispatch('open-sale-detail', id: $id);
    }

    public function render(): View
    {
        $sales = Sale::query()
            ->with(['client:id,display_name,document_number', 'user:id,name'])
            ->withSum('details as units_count', 'quantity')
            ->when(in_array($this->status, [Sale::STATUS_COMPLETED, Sale::STATUS_CANCELLED]),
                fn ($q) => $q->where('status', $this->status))
            ->when($this->onlyPendingInvoice, fn ($q) => $q->where('has_invoice_pending', true)
                ->where('status', Sale::STATUS_COMPLETED))
            ->when(filled($this->search), function ($q) {
                $term = '%'.trim($this->search).'%';
                $q->where(function ($q) use ($term) {
                    $q->where('code', 'like', $term)
                        ->orWhere('document_number', 'like', $term)
                        ->orWhereHas('client', fn ($c) => $c->where('display_name', 'like', $term)
                            ->orWhere('document_number', 'like', $term));
                });
            })
            ->latest('sale_date')
            ->latest('id')
            ->paginate(15);

        return view('livewire.sale.sale-index', [
            'sales' => $sales,
            'pendingInvoiceCount' => Sale::where('status', Sale::STATUS_COMPLETED)->where('has_invoice_pending', true)->count(),
        ]);
    }
}
