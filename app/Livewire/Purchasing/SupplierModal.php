<?php

namespace App\Livewire\Purchasing;

use App\Models\Supplier;
use App\Services\Core\AccountRule;
use App\Services\Purchasing\SupplierService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class SupplierModal extends Component
{
    public bool $show = false;

    public ?int $supplierId = null;

    // Por defecto un proveedor es empresa: persona jurídica con RUC.
    public string $type = 'company';

    public string $document_type = 'RUC';

    public string $document_number = '';

    // Muestra los datos opcionales (contacto, banco, notas, estado).
    public bool $advanced = false;

    public ?string $names = null;

    public ?string $paternal_last_name = null;

    public ?string $maternal_last_name = null;

    public ?string $company_name = null;

    public ?string $mobile = null;

    public ?string $phone = null;

    public ?string $email = null;

    public ?string $address = null;

    public ?string $bank_name = null;

    public ?string $account_number = null;

    public ?string $cci = null;

    public ?string $notes = null;

    public bool $is_active = true;

    #[On('open-supplier-modal')]
    public function open(?int $supplierId = null): void
    {
        $this->resetValidation();
        $this->resetExcept('show');
        $this->supplierId = $supplierId;

        if ($supplierId) {
            $supplier = Supplier::findOrFail($supplierId);

            $this->fill($supplier->only([
                'type', 'document_type', 'document_number', 'names', 'paternal_last_name', 'maternal_last_name',
                'company_name', 'mobile', 'phone', 'email', 'address', 'bank_name', 'account_number', 'cci', 'notes',
            ]));
            $this->is_active = $supplier->is_active;

            // Si ya tiene datos opcionales, se muestra la sección expandida.
            $this->advanced = collect($supplier->only([
                'mobile', 'phone', 'email', 'address', 'bank_name', 'account_number', 'cci', 'notes',
            ]))->filter()->isNotEmpty() || ! $supplier->is_active;
        }

        $this->show = true;
    }

    public function setType(string $type): void
    {
        $this->type = $type === 'individual' ? 'individual' : 'company';
        $this->document_type = $this->type === 'company' ? 'RUC' : 'DNI';
        $this->resetValidation();
    }

    protected function rules(): array
    {
        return [
            'type' => 'required|in:individual,company',
            'document_type' => $this->type === 'company'
                ? 'required|in:RUC'
                : 'required|in:DNI,RUC,CE,PASAPORTE',
            'document_number' => [
                'required',
                'string',
                'max:30',
                AccountRule::unique('suppliers', 'document_number')
                    ->where('document_type', $this->document_type)
                    ->ignore($this->supplierId),
            ],
            'names' => 'nullable|required_if:type,individual|string|max:255',
            'paternal_last_name' => 'nullable|required_if:type,individual|string|max:255',
            'maternal_last_name' => 'nullable|string|max:255',
            'company_name' => 'nullable|required_if:type,company|string|max:255',
            'mobile' => 'nullable|string|max:30',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'bank_name' => 'nullable|string|max:255',
            'account_number' => 'nullable|string|max:255',
            'cci' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
        ];
    }

    public function save(SupplierService $service): void
    {
        $this->authorize('proveedores.registrar');

        $data = $this->validate();

        if ($this->supplierId) {
            $supplier = Supplier::findOrFail($this->supplierId);
            $service->actualizar($supplier, $data);
            $this->dispatch('supplier-saved');
        } else {
            $supplier = $service->crear($data);
            // El formulario de compra lo escucha para dejarlo seleccionado.
            $this->dispatch('supplier-created', supplierId: $supplier->id);
            $this->dispatch('supplier-saved');
        }

        $this->show = false;
        Flux::toast(variant: 'success', text: 'Proveedor guardado correctamente.');
    }

    public function render(): View
    {
        return view('livewire.purchasing.supplier-modal');
    }
}
