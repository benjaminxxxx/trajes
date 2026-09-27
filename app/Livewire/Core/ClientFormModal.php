<?php
namespace App\Livewire\Core;

use App\Models\Client;
use Flux\Flux;
use Livewire\Component;
use Str;

class ClientFormModal extends Component
{
    public ?int $editingId = null;

    // Propiedades entrelazadas con Alpine
    public string $type = 'individual';
    public bool $advanced = false;

    // Campos del formulario
    public string $document_type = 'DNI';
    public string $document_number = '';
    public ?string $names = null;
    public ?string $last_name = null;
    public ?string $company_name = null;
    public ?string $email = null;
    public ?string $mobile = null;
    public ?string $address = null;
    public ?string $birth_date = null;
    public ?string $gender = null;
    public ?string $notes = null;
    public bool $isClientFormOpen = false;

    protected $listeners = ['open-client-modal' => 'loadClient'];

    public function loadClient(?int $id = null)
    {
        $this->resetValidation();
        $this->reset();

        if ($id) {
            $this->editingId = $id;
            $client = Client::findOrFail($id);

            $this->type = $client->type;
            $this->document_type = $client->document_type;
            $this->document_number = $client->document_number;
            $this->names = $client->names;
            $this->last_name = $client->last_name;
            $this->company_name = $client->company_name;
            $this->email = $client->email;
            $this->mobile = $client->mobile;
            $this->address = $client->address;
            $this->birth_date = $client->birth_date?->format('Y-m-d');
            $this->gender = $client->gender;
            $this->notes = $client->notes;

            // Si tiene datos opcionales llenos, expandir la sección avanzada
            $this->advanced = (bool) ($client->email || $client->mobile || $client->address || $client->notes);
        }

        $this->isClientFormOpen = true;
    }

    public function save()
    {
        // Reglas de validación condicionales
        $rules = [
            'type' => 'required|in:individual,company',
            'document_type' => 'required|string',
            'document_number' => 'required|string|unique:clients,document_number,' . $this->editingId,
        ];

        if ($this->type === 'individual') {
            $rules['names'] = 'required|string|max:255';
            $rules['last_name'] = 'required|string|max:255';
        } else {
            $rules['company_name'] = 'required|string|max:255';
        }

        $this->validate($rules);

        try {
            // Lógica para calcular el display_name cacheado
            $displayName = $this->type === 'individual'
                ? trim("{$this->names} {$this->last_name}")
                : $this->company_name;

            // Crear/Actualizar en base de datos
            Client::updateOrCreate(
                ['id' => $this->editingId],
                [
                    'uuid' => $this->editingId ? Client::find($this->editingId)->uuid : (string) Str::uuid(),
                    'type' => $this->type,
                    'document_type' => $this->document_type,
                    'document_number' => $this->document_number,
                    'names' => $this->names,
                    'last_name' => $this->last_name,
                    'company_name' => $this->company_name,
                    'display_name' => $displayName,
                    'email' => $this->email,
                    'mobile' => $this->mobile,
                    'address' => $this->address,
                    'birth_date' => $this->birth_date,
                    'gender' => $this->gender,
                    'notes' => $this->notes,
                ]
            );

            $this->isClientFormOpen = false;;
            $this->dispatch('clientSaved');
            Flux::toast(variant: 'success',text: 'Cliente guardado correctamente.');
        } catch (\Throwable $th) {
            Flux::toast(variant: 'danger',text: $th->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.core.client-form-modal');
    }
}