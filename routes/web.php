<?php

use App\Livewire\Core\ClientIndex;
use App\Livewire\Core\Dashboard;
use App\Livewire\Core\UserIndex;
use App\Livewire\Finance\CashRegisterShift;
use App\Livewire\Finance\ExpenseIndex;
use App\Livewire\Finance\PaymentIndex;
use App\Livewire\Inventory\CategoryIndex;
use App\Livewire\Inventory\PhysicalItemTracker;
use App\Livewire\Inventory\ProductForm;
use App\Livewire\Inventory\ProductIndex;
use App\Livewire\Maintenance\LaundryIndex;
use App\Livewire\Maintenance\MaintenanceHistory;
use App\Livewire\Purchasing\PurchaseForm;
use App\Livewire\Purchasing\PurchaseIndex;
use App\Livewire\Purchasing\SupplierIndex;
use App\Livewire\Rental\RentalForm;
use App\Livewire\Rental\RentalIndex;
use App\Livewire\Rental\RentalReturn;
use App\Livewire\Sale\SaleForm;
use App\Livewire\Sale\SaleIndex;
use Illuminate\Support\Facades\Route;

/*
| Todo el sistema requiere sesión: un invitado que entra a / va al login y,
| al iniciar sesión, llega al dashboard. Cada ruta exige su permiso
| (config/permissions.php); el rol admin pasa siempre por Gate::before.
*/
Route::middleware(['auth', 'verified'])->group(function () {

    Route::redirect('/', '/dashboard')->name('home');
    Route::get('dashboard', Dashboard::class)->name('dashboard');

    // ==========================================
    // 1. DOMINIO: INVENTARIO (Inventory)
    // ==========================================
    Route::middleware('can:inventario.categorias')->group(function () {
        Route::get('inventario/categorias', CategoryIndex::class)->name('inventory.categories.index');
    });
    Route::middleware('can:inventario.productos')->group(function () {
        Route::get('inventario/productos', ProductIndex::class)->name('inventory.products.index');
        Route::get('inventario/productos/nuevo', ProductForm::class)->name('inventory.products.create');
        Route::get('inventario/productos/{product}/editar', ProductForm::class)->name('inventory.products.edit');
    });
    Route::get('inventario/prendas-fisicas', PhysicalItemTracker::class)->name('inventory.physical-items.index')
        ->middleware('can:inventario.seguimiento');

    // ==========================================
    // 2. DOMINIO: ALQUILERES (Rental)
    // ==========================================
    Route::get('alquileres', RentalIndex::class)->name('rental.rentals.index')->middleware('can:alquileres.ver');
    Route::get('alquileres/nuevo', RentalForm::class)->name('rental.rentals.create')->middleware('can:alquileres.registrar');
    Route::get('alquileres/{rental}/devolucion', RentalReturn::class)->name('rental.rentals.return')
        ->middleware('can:alquileres.recepcionar');

    // ==========================================
    // 3. DOMINIO: VENTAS (Sale)
    // ==========================================
    Route::get('ventas', SaleIndex::class)->name('sale.sales.index')->middleware('can:ventas.ver');
    Route::get('ventas/nueva', SaleForm::class)->name('sale.sales.create')->middleware('can:ventas.registrar');

    // ==========================================
    // 4. DOMINIO: COMPRAS (Purchasing)
    // ==========================================
    Route::get('compras/proveedores', SupplierIndex::class)->name('purchasing.suppliers.index')->middleware('can:proveedores.ver');
    Route::get('compras', PurchaseIndex::class)->name('purchasing.purchases.index')->middleware('can:compras.ver');
    Route::get('compras/nueva', PurchaseForm::class)->name('purchasing.purchases.create')->middleware('can:compras.registrar');

    // ==========================================
    // 5. DOMINIO: MANTENIMIENTO (Maintenance)
    // ==========================================
    Route::get('mantenimiento/despacho-lavanderia', LaundryIndex::class)->name('maintenance.laundry.index')
        ->middleware('can:mantenimiento.registrar');
    Route::get('mantenimientos', MaintenanceHistory::class)->name('maintenance.history.index')->middleware('can:mantenimiento.ver');

    // ==========================================
    // 6. DOMINIO: FINANZAS Y CAJA (Finance)
    // ==========================================
    Route::get('finanzas/caja-chica', CashRegisterShift::class)->name('finance.cash.index')->middleware('can:finanzas.caja');
    Route::get('finanzas/pagos-cobros', PaymentIndex::class)->name('finance.payments.index')->middleware('can:finanzas.pagos');
    Route::get('finanzas/gastos', ExpenseIndex::class)->name('finance.expenses.index')->middleware('can:finanzas.gastos');

    // ==========================================
    // 7. DOMINIO: TRANSVERSAL (Core)
    // ==========================================
    Route::get('clientes', ClientIndex::class)->name('core.clients.index')->middleware('can:clientes.ver');
    Route::get('seguridad/usuarios', UserIndex::class)->name('core.users.index')->middleware('can:seguridad.usuarios');

});

require __DIR__.'/settings.php';
