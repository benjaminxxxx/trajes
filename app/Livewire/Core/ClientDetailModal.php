<?php 
namespace App\Livewire\Core;

use App\Models\Client;
use Flux\Flux;
use Livewire\Component;
use Livewire\Attributes\On;

class ClientDetailModal extends Component
{
    public ?Client $client = null;
    public bool $show = false;

    #[On('open-client-detail')]
    public function loadClient(int $id): void
    {
        $this->client = Client::withCount(['sales', 'rentals'])->findOrFail($id);
        $this->show = true;
    }

    public function closeModal(): void
    {
        $this->show = false;
        $this->client = null;
    }

    public function openEdit(): void
    {
        $clientId = $this->client->id;
        $this->closeModal();
        $this->dispatch('open-client-modal', id: $clientId);
    }

    public function archiveClient(): void
    {
        if ($this->client) {
            $this->client->delete(); // Soft delete
            $this->dispatch('clientSaved');
            Flux::toast(variant: 'success',text: 'Cliente dado de baja.');
            $this->closeModal();
        }
    }

    public function render()
    {
        return view('livewire.core.client-detail-modal');
    }
}