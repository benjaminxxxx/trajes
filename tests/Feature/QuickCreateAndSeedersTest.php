<?php

use App\Livewire\Core\ClientFormModal;
use App\Livewire\Purchasing\PurchaseForm;
use App\Livewire\Purchasing\SupplierModal;
use App\Livewire\Rental\RentalForm;
use App\Livewire\Sale\SaleForm;
use App\Models\Client;
use App\Models\Supplier;
use Database\Seeders\CategoryAndAttributeSeeder;
use Database\Seeders\ClientSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Database\Seeders\SupplierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(createAdmin());
});

test('creating a client from the modal announces it and the sale and rental forms select it', function () {
    $modal = Livewire::test(ClientFormModal::class)
        ->call('loadClient')
        ->set('document_number', '45872310')
        ->set('names', 'Ana María')
        ->set('last_name', 'Morales')
        ->call('save')
        ->assertDispatched('client-created');

    $client = Client::where('document_number', '45872310')->firstOrFail();

    Livewire::test(SaleForm::class)
        ->call('selectCreatedClient', $client->id)
        ->assertSet('client_id', $client->id)
        ->assertSet('client_name', 'Ana María Morales')
        ->assertSet('clientPickerVersion', 1);

    Livewire::test(RentalForm::class)
        ->call('selectCreatedClient', $client->id)
        ->assertSet('client_id', $client->id);

    // Editar no debe re-seleccionar nada en los formularios.
    $modal->call('loadClient', $client->id)
        ->set('names', 'Ana')
        ->call('save')
        ->assertNotDispatched('client-created');
});

test('supplier modal defaults to a company with RUC and only asks for the essentials', function () {
    Livewire::test(SupplierModal::class)
        ->call('open')
        ->assertSet('type', 'company')
        ->assertSet('document_type', 'RUC')
        ->assertSet('advanced', false)
        ->set('document_number', '20601112223')
        ->set('company_name', 'Telas del Norte S.A.C.')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('supplier-created');

    $supplier = Supplier::where('document_number', '20601112223')->firstOrFail();

    expect($supplier->display_name)->toBe('Telas del Norte S.A.C.')
        ->and($supplier->code)->toStartWith('PROV-');

    Livewire::test(PurchaseForm::class)
        ->call('selectCreatedSupplier', $supplier->id)
        ->assertSet('supplier_id', $supplier->id)
        ->assertSet('supplier_name', 'Telas del Norte S.A.C.')
        ->assertSet('supplierPickerVersion', 1);
});

test('a company supplier only accepts RUC', function () {
    Livewire::test(SupplierModal::class)
        ->call('open')
        ->set('document_type', 'DNI')
        ->set('document_number', '12345678')
        ->set('company_name', 'Empresa')
        ->call('save')
        ->assertHasErrors('document_type');
});

test('switching the supplier to a natural person switches the document to DNI', function () {
    Livewire::test(SupplierModal::class)
        ->call('open')
        ->call('setType', 'individual')
        ->assertSet('document_type', 'DNI')
        ->set('document_number', '40125873')
        ->set('names', 'Julio')
        ->set('paternal_last_name', 'Mendoza')
        ->call('save')
        ->assertHasNoErrors();

    expect(Supplier::where('document_number', '40125873')->value('display_name'))->toBe('Julio Mendoza');
});

test('client and supplier seeders are idempotent and share the catalog supplier', function () {
    Storage::fake('public');

    $this->seed([ClientSeeder::class, SupplierSeeder::class]);
    $this->seed([ClientSeeder::class, SupplierSeeder::class, CategoryAndAttributeSeeder::class, ProductCatalogSeeder::class]);

    expect(Client::count())->toBe(10)
        ->and(Client::where('type', 'company')->count())->toBe(2)
        ->and(Supplier::count())->toBe(5)
        ->and(Supplier::where('code', 'like', 'PROV-%')->count())->toBe(5);
});
