<?php

use App\Livewire\Sale\SaleForm;
use Illuminate\Support\Facades\Route;

// Dominio: Core
use App\Livewire\Core\UserIndex;
use App\Livewire\Core\ClientIndex;

// Dominio: Inventory
use App\Livewire\Inventory\CategoryIndex;
use App\Livewire\Inventory\ProductIndex;
use App\Livewire\Inventory\ProductForm;
use App\Livewire\Inventory\PhysicalItemTracker;

// Dominio: Rental
use App\Livewire\Rental\RentalIndex;
use App\Livewire\Rental\RentalForm;
use App\Livewire\Rental\RentalReturn;

// Dominio: Sale
use App\Livewire\Sale\PosTerminal;
use App\Livewire\Sale\SaleIndex;

// Dominio: Purchasing
use App\Livewire\Purchasing\SupplierIndex;
use App\Livewire\Purchasing\PurchaseIndex;
use App\Livewire\Purchasing\PurchaseForm;

// Dominio: Maintenance
use App\Livewire\Maintenance\LaundryIndex;
use App\Livewire\Maintenance\MaintenanceHistory;

// Dominio: Finance
use App\Livewire\Finance\CashRegisterShift;
use App\Livewire\Finance\PaymentIndex;
use App\Livewire\Finance\ExpenseIndex;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {

    Route::view('dashboard', 'dashboard')->name('dashboard');

    // ==========================================
    // 1. DOMINIO: INVENTARIO (Inventory)
    // ==========================================

    Route::get('inventario/categorias', CategoryIndex::class)->name('inventory.categories.index');
    Route::get('inventario/productos', ProductIndex::class)->name('inventory.products.index');
    Route::get('inventario/productos/nuevo', ProductForm::class)->name('inventory.products.create');
    Route::get('inventario/productos/{product}/editar', ProductForm::class)->name('inventory.products.edit');
    Route::get('inventario/prendas-fisicas', PhysicalItemTracker::class)->name('inventory.physical-items.index');

    // ==========================================
    // 2. DOMINIO: ALQUILERES (Rental)
    // ==========================================
    Route::get('alquileres', RentalIndex::class)->name('rental.rentals.index');
    Route::get('alquileres/nuevo', RentalForm::class)->name('rental.rentals.create');
    Route::get('alquileres/{rental}/editar', RentalForm::class)->name('rental.rentals.edit');
    Route::get('alquileres/{rental}/devolucion', RentalReturn::class)->name('rental.rentals.return');

    // ==========================================
    // 3. DOMINIO: VENTAS (Sale)
    // ==========================================
    Route::get('ventas/pos', SaleForm::class)->name('sale.pos');
    Route::get('ventas/historial', SaleIndex::class)->name('sale.sales.index');

    // ==========================================
    // 4. DOMINIO: COMPRAS (Purchasing)
    // ==========================================
    Route::get('compras/proveedores', SupplierIndex::class)->name('purchasing.suppliers.index');
    Route::get('compras', PurchaseIndex::class)->name('purchasing.purchases.index');
    Route::get('compras/nueva', PurchaseForm::class)->name('purchasing.purchases.create');
    Route::get('compras/{purchase}/editar', PurchaseForm::class)->name('purchasing.purchases.edit');

    // ==========================================
    // 5. DOMINIO: MANTENIMIENTO / LAVANDERÍA (Maintenance)
    // ==========================================
    Route::get('mantenimiento/despacho-lavanderia', LaundryIndex::class)->name('maintenance.laundry.index');
    Route::get('mantenimiento/historial', MaintenanceHistory::class)->name('maintenance.history.index');

    // ==========================================
    // 6. DOMINIO: FINANZAS Y CAJA (Finance)
    // ==========================================
    Route::get('finanzas/caja-chica', CashRegisterShift::class)->name('finance.cash.index');
    Route::get('finanzas/pagos-cobros', PaymentIndex::class)->name('finance.payments.index');
    Route::get('finanzas/gastos', ExpenseIndex::class)->name('finance.expenses.index');

    // ==========================================
    // 7. DOMINIO: TRANSVERSAL (Core)
    // ==========================================
    Route::get('clientes', ClientIndex::class)->name('core.clients.index');
    Route::get('seguridad/usuarios', UserIndex::class)->name('core.users.index');

});

require __DIR__ . '/settings.php';