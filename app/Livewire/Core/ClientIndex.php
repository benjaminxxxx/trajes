<?php
namespace App\Livewire\Core;

use App\Models\Client;
use Flux\Flux;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Url;

class ClientIndex extends Component
{
    use WithPagination;

    #[Url(history: true)]
    public string $search = '';

    #[Url(history: true)]
    public string $filter = 'all'; // all, individual, company

    #[Url(history: true)]
    public bool $showArchived = false;

    // Escucha eventos de guardado para refrescar el listado
    protected $listeners = ['clientSaved' => '$refresh'];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilter(): void
    {
        $this->resetPage();
    }

    public function updatingShowArchived(): void
    {
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        $this->dispatch('open-client-modal');
    }

    public function openEditModal(int $id): void
    {
        $this->dispatch('open-client-detail', id: $id);
    }

    public function restoreClient(int $id): void
    {
        $client = Client::onlyTrashed()->findOrFail($id);
        $client->restore();

        Flux::toast(variant: 'success', text: 'Cliente restaurado correctamente.');
    }

    public function forceDeleteClient(int $id): void
    {
        $client = Client::onlyTrashed()->findOrFail($id);
        $client->forceDelete();
        Flux::toast(variant: 'success', text: 'Cliente eliminado permanentemente.');
    }

    public function render()
    {
        $query = $this->showArchived
            ? Client::onlyTrashed()
            : Client::query();

        // Aplicar filtro por tipo
        if (in_array($this->filter, ['individual', 'company'])) {
            $query->where('type', $this->filter);
        }

        // Aplicar búsqueda por nombre, apellido, razón social o número de documento
        if (!empty($this->search)) {
            $searchTerm = '%' . trim($this->search) . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('names', 'like', $searchTerm)
                    ->orWhere('last_name', 'like', $searchTerm)
                    ->orWhere('company_name', 'like', $searchTerm)
                    ->orWhere('display_name', 'like', $searchTerm)
                    ->orWhere('document_number', 'like', $searchTerm);
            });
        }

        $clients = $query->latest()->paginate(8);

        return view('livewire.core.client-index', [
            'clients' => $clients,
        ]);
    }
}