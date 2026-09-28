<?php

use App\Livewire\Maintenance\MaintenanceFormModal;
use App\Livewire\Maintenance\MaintenanceHistory;
use App\Livewire\Sale\SaleDetailModal;
use App\Models\ItemMaintenance;
use App\Models\OperationalExpense;
use App\Models\Rental;
use App\Models\Sale;
use App\Services\Core\DashboardService;
use App\Services\Core\NavigationMenu;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// ---------------- Rutas de entrada y login ----------------

test('guests visiting the root are sent to the split login and users to the dashboard', function () {
    $this->get('/')->assertRedirect(route('login'));

    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Alquiler y venta de trajes');

    $this->actingAs(createSeller())->get('/')->assertRedirect('/dashboard');
});

// ---------------- Permisos del vendedor ----------------

test('sellers only reach sales, rentals, clients and the dashboard', function () {
    $this->actingAs(createSeller());

    foreach (['dashboard', 'sale.sales.index', 'sale.sales.create', 'rental.rentals.index', 'rental.rentals.create', 'core.clients.index'] as $route) {
        $this->get(route($route))->assertOk();
    }

    foreach ([
        'purchasing.purchases.index', 'purchasing.purchases.create', 'purchasing.suppliers.index',
        'inventory.products.index', 'inventory.categories.index', 'inventory.physical-items.index',
        'maintenance.history.index', 'finance.expenses.index', 'finance.cash.index', 'core.users.index',
    ] as $route) {
        $this->get(route($route))->assertForbidden();
    }
});

test('admins reach every module without assigned permissions', function () {
    $admin = createAdmin();

    expect($admin->getAllPermissions())->toBeEmpty();

    $this->actingAs($admin);

    foreach (['purchasing.purchases.index', 'purchasing.suppliers.index', 'inventory.physical-items.index', 'maintenance.history.index', 'core.users.index'] as $route) {
        $this->get(route($route))->assertOk();
    }
});

test('the menu only lists what the user can open', function () {
    $sellerMenu = app(NavigationMenu::class)->for(createSeller());
    $labels = collect($sellerMenu)->pluck('label')->all();
    $commercial = collect($sellerMenu)->firstWhere('label', 'Comercial');

    expect($labels)->toBe(['Dashboard', 'Comercial'])
        ->and(collect($commercial['items'])->pluck('route')->all())
        ->toBe(['sale.sales.create', 'sale.sales.index', 'rental.rentals.create', 'rental.rentals.index', 'core.clients.index']);

    $adminLabels = collect(app(NavigationMenu::class)->for(createAdmin()))->pluck('label')->all();

    expect($adminLabels)->toBe(['Dashboard', 'Comercial', 'Inventario', 'Compras', 'Administración']);
});

test('sellers see sales read only and cannot edit or cancel them', function () {
    $this->actingAs(createAdmin());
    $variant = createProductIn(null);
    sellVariant($variant, now()->toDateString(), quantity: 1);
    $sale = Sale::first();

    Livewire::actingAs(createSeller())
        ->test(SaleDetailModal::class)
        ->call('loadSale', $sale->id)
        ->assertSee('solicita la corrección o anulación al administrador')
        ->assertDontSee('Guardar cambios')
        ->set('notes', 'Cambio no permitido')
        ->call('save')
        ->assertForbidden();
});

// ---------------- Mantenimientos ----------------

test('a maintenance is registered for a physical unit and its status updated', function () {
    $this->actingAs(createAdmin());
    $unit = createPhysicalUnit(createProductIn(null));

    Livewire::test(MaintenanceFormModal::class)
        ->call('open', $unit->id)
        ->assertSet('after_status', 'maintenance')
        ->set('type', 'tailor_repair')
        ->set('cost', '35.50')
        ->set('provider_name', 'Sastre Julio')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('maintenanceSaved');

    $maintenance = ItemMaintenance::firstOrFail();

    expect((float) $maintenance->cost)->toEqual(35.5)
        ->and($maintenance->maintenance_date->toDateString())->toBe(now()->toDateString())
        ->and($unit->fresh()->status)->toBe('maintenance');

    Livewire::test(MaintenanceHistory::class)
        ->assertSee($unit->code)
        ->assertSee('35.50')
        ->call('deleteMaintenance', $maintenance->id);

    expect(ItemMaintenance::count())->toBe(0);
});

test('maintenance requires choosing a unit', function () {
    $this->actingAs(createAdmin());

    Livewire::test(MaintenanceFormModal::class)
        ->call('open')
        ->set('cost', '10')
        ->call('save')
        ->assertHasErrors('unitId');
});

test('opening maintenances from a unit sheet preselects that unit', function () {
    $this->actingAs(createAdmin());
    $unit = createPhysicalUnit(createProductIn(null));

    Livewire::withQueryParams(['prenda' => $unit->id])
        ->test(MaintenanceHistory::class)
        ->assertDispatched('open-maintenance-modal', unitId: $unit->id);
});

// ---------------- Ganancia del mes ----------------

test('monthly profit adds rentals and damage charges and subtracts maintenance, expenses and write offs', function () {
    $this->actingAs(createAdmin());
    $variant = createProductIn(null);

    // Venta: subtotal 40 (2 × 20) a costo 10 c/u → margen 20.
    sellVariant($variant, '2026-05-10', quantity: 2);
    // Alquiler recepcionado con cobro por daños de 25 (rental_total 0 en el helper).
    rentVariant($variant, '2026-05-11');
    Rental::first()->update(['rental_total' => 150, 'actual_return_date' => '2026-05-12', 'penalty_total' => 25]);
    // Mantenimiento 30, gasto operativo 50, prenda perdida con costo 80.
    $unit = createPhysicalUnit($variant);
    ItemMaintenance::create(['physical_product_id' => $unit->id, 'type' => 'laundromat', 'cost' => 30, 'maintenance_date' => '2026-05-15']);
    OperationalExpense::create(['category' => 'Luz', 'concept' => 'Recibo', 'amount' => 50, 'expense_date' => '2026-05-20']);
    $lost = createPhysicalUnit($variant);
    $lost->forceFill(['purchase_cost' => 80])->save();
    $this->travelTo(CarbonImmutable::parse('2026-05-25'));
    $lost->update(['status' => 'lost']);
    $this->travelBack();

    $month = CarbonImmutable::parse('2026-05-01');
    $stats = app(DashboardService::class)->periodStats($month->startOfMonth(), $month->endOfMonth());

    expect($stats['sales_margin'])->toEqual(20.0)
        ->and($stats['rentals_total'])->toEqual(150.0)
        ->and($stats['damage_charges'])->toEqual(25.0)
        ->and($stats['maintenance_cost'])->toEqual(30.0)
        ->and($stats['expenses'])->toEqual(50.0)
        ->and($stats['disposal_losses'])->toEqual(80.0)
        ->and($stats['profit'])->toEqual(35.0); // 20 + 150 + 25 − 30 − 50 − 80
});
