<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Billing\SunatDocumentPdfController;
use App\Http\Controllers\Documents\ReceiptController;
use App\Http\Controllers\Rental\RentalMediaController;
use App\Livewire\Billing\BillingSettings;
use App\Livewire\Billing\SunatDocumentIndex;
use App\Livewire\Core\AccountPicker;
use App\Livewire\Core\AuditIndex;
use App\Livewire\Core\ClientIndex;
use App\Livewire\Core\Dashboard;
use App\Livewire\Core\SystemSettings;
use App\Livewire\Core\UserIndex;
use App\Livewire\Finance\CashIndex;
use App\Livewire\Finance\ExpenseIndex;
use App\Livewire\Inventory\AssetIndex;
use App\Livewire\Inventory\AttributeCatalog;
use App\Livewire\Inventory\CategoryIndex;
use App\Livewire\Inventory\CompanyValuation;
use App\Livewire\Inventory\PhysicalItemTracker;
use App\Livewire\Inventory\ProductIndex;
use App\Livewire\Maintenance\LaundryIndex;
use App\Livewire\Maintenance\MaintenanceHistory;
use App\Livewire\Platform\AccountIndex as PlatformAccountIndex;
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
/*
| Panel de la plataforma (Kipusoft): todos los negocios, su estado y su uso.
| No depende de tener un negocio propio.
*/
Route::get('plataforma', PlatformAccountIndex::class)->name('platform.accounts.index')
    ->middleware(['auth', 'verified', 'platform']);

/*
| Iniciar sesión o registrarse con Google.
*/
Route::middleware('guest')->group(function () {
    Route::get('auth/google', [GoogleAuthController::class, 'redirect'])->name('auth.google');
    Route::get('auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');
});

/*
| Elegir o crear el negocio con el que se trabaja. Es lo único accesible sin
| un negocio activo (ej. recién registrado, o retirado de su único negocio).
*/
Route::get('cuentas', AccountPicker::class)->name('accounts.select')->middleware(['auth', 'verified']);

Route::middleware(['auth', 'verified', 'account'])->group(function () {

    Route::redirect('/', '/dashboard')->name('home');
    Route::get('dashboard', Dashboard::class)->name('dashboard');

    // ==========================================
    // 1. DOMINIO: INVENTARIO (Inventory)
    // ==========================================
    Route::middleware('can:inventario.categorias')->group(function () {
        Route::get('inventario/categorias', CategoryIndex::class)->name('inventory.categories.index');
    });
    Route::get('inventario/atributos', AttributeCatalog::class)->name('inventory.attributes.index')
        ->middleware('can:inventario.atributos');
    Route::middleware('can:inventario.productos')->group(function () {
        Route::get('inventario/productos', ProductIndex::class)->name('inventory.products.index');
    });
    Route::get('inventario/prendas-fisicas', PhysicalItemTracker::class)->name('inventory.physical-items.index')
        ->middleware('can:inventario.seguimiento');
    Route::get('inventario/bienes', AssetIndex::class)->name('inventory.assets.index')
        ->middleware('can:inventario.bienes');
    Route::get('inventario/valorizado', CompanyValuation::class)->name('inventory.valuation.index')
        ->middleware('can:inventario.valorizado');

    // ==========================================
    // 2. DOMINIO: ALQUILERES (Rental)
    // ==========================================
    Route::get('alquileres', RentalIndex::class)->name('rental.rentals.index')->middleware('can:alquileres.ver');
    Route::get('alquileres/nuevo', RentalForm::class)->name('rental.rentals.create')->middleware('can:alquileres.registrar');
    Route::get('alquileres/{rental}/devolucion', RentalReturn::class)->name('rental.rentals.return')
        ->middleware('can:alquileres.recepcionar');
    Route::get('alquileres/documentos/{media}', RentalMediaController::class)->name('rental.media.show')
        ->middleware('can:alquileres.ver');

    // ==========================================
    // 3. DOMINIO: VENTAS (Sale)
    // ==========================================
    Route::get('ventas', SaleIndex::class)->name('sale.sales.index')->middleware('can:ventas.ver');
    Route::get('ventas/nueva', SaleForm::class)->name('sale.sales.create')->middleware('can:ventas.registrar');

    // Comprobantes PDF (el permiso se valida según el tipo: ventas.ver / alquileres.ver).
    Route::get('comprobantes/{type}/{id}', [ReceiptController::class, 'show'])->name('receipts.show')
        ->whereIn('type', ['venta', 'alquiler'])->whereNumber('id');
    Route::get('comprobantes/{type}/{id}/pdf', [ReceiptController::class, 'file'])->name('receipts.file')
        ->whereIn('type', ['venta', 'alquiler'])->whereNumber('id');

    // ==========================================
    // 3.1 FACTURACIÓN ELECTRÓNICA (Billing) — vía el servicio central de facturación
    // ==========================================
    Route::get('facturacion/comprobantes', SunatDocumentIndex::class)->name('billing.documents.index')
        ->middleware('can:facturacion.emitir');
    Route::get('facturacion/comprobantes/{sunatDocument}/{format}', SunatDocumentPdfController::class)
        ->name('billing.documents.pdf')->whereIn('format', ['a4', 'ticket'])->middleware('can:facturacion.emitir');
    Route::get('facturacion/configuracion', BillingSettings::class)->name('billing.settings')
        ->middleware('can:facturacion.configurar');

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
    Route::get('finanzas/caja', CashIndex::class)->name('finance.cash.index')->middleware('can:caja.ver');
    Route::get('finanzas/gastos', ExpenseIndex::class)->name('finance.expenses.index')->middleware('can:finanzas.gastos');

    // ==========================================
    // 7. DOMINIO: TRANSVERSAL (Core)
    // ==========================================
    Route::get('clientes', ClientIndex::class)->name('core.clients.index')->middleware('can:clientes.ver');
    Route::get('seguridad/usuarios', UserIndex::class)->name('core.users.index')->middleware('can:seguridad.usuarios');
    Route::get('configuracion', SystemSettings::class)->name('core.settings.index')->middleware('can:configuracion.general');
    Route::get('seguridad/auditoria', AuditIndex::class)->name('core.audit.index')->middleware('can:auditoria.ver');

});

/*
| Enlace público del comprobante que recibe el cliente por WhatsApp: no pide
| sesión, pero está firmado y vence (config business.receipt_link_days).
*/
Route::get('c/{type}/{id}', [ReceiptController::class, 'public'])->name('receipts.public')
    ->whereIn('type', ['venta', 'alquiler'])->whereNumber('id')
    ->middleware(['signed', 'throttle:30,1']);

require __DIR__.'/settings.php';
