<?php

namespace App\Livewire\Purchasing;

use App\Models\Supplier;
use App\Services\Purchasing\SupplierService;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

class SupplierModal extends Component
{
    public bool $show = false;
    public ?int $supplierId = null;

    public string $type = 'individual';
    public string $document_type = 'DNI';
    public string $document_number = '';
    
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
        $this->supplierId = $supplierId;

        if ($supplierId) {
            $supplier = Supplier::findOrFail($supplierId);
            $this->type = $supplier->type;
            $this->document_type = $supplier->document_type;
            $this->document_number = $supplier->document_number;
            $this->names = $supplier->names;
            $this->paternal_last_name = $supplier->paternal_last_name;
            $this->maternal_last_name = $supplier->maternal_last_name;
            $this->company_name = $supplier->company_name;
            $this->mobile = $supplier->mobile;
            $this->phone = $supplier->phone;
            $this->email = $supplier->email;
            $this->address = $supplier->address;
            $this->bank_name = $supplier->bank_name;
            $this->account_number = $supplier->account_number;
            $this->cci = $supplier->cci;
            $this->notes = $supplier->notes;
            $this->is_active = $supplier->is_active;
        } else {
            $this->resetExcept(['show']);
            $this->type = 'individual';
            $this->document_type = 'DNI';
            $this->is_active = true;
        }

        $this->show = true;
    }

    public function updatedType($value): void
    {
        $this->document_type = $value === 'company' ? 'RUC' : 'DNI';
    }

    protected function rules(): array
    {
        return [
            'type' => 'required|in:individual,company',
            'document_type' => 'required|string|max:20',
            'document_number' => [
                'required',
                'string',
                'max:30',
                Rule::unique('suppliers', 'document_number')
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
        $data = $this->validate();

        if ($this->supplierId) {
            $supplier = Supplier::findOrFail($this->supplierId);
            $service->actualizar($supplier, $data);
            $this->dispatch('supplier-saved');
        } else {
            $supplier = $service->crear($data);
            $this->dispatch('supplier-created', supplierId: $supplier->id);
            $this->dispatch('supplier-saved');
        }

        $this->show = false;
    }

    public function render()
    {
        return view('livewire.purchasing.supplier-modal');
    }
}