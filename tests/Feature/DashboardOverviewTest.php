<?php

use App\Livewire\Core\Dashboard;
use App\Models\OperationalExpense;
use App\Models\User;
use App\Services\Core\DashboardService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    $this->actingAs($this->admin);
});

test('dashboard page renders with shortcuts to sell and rent', function () {
    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('sale.sales.create'))
        ->assertSee(route('rental.rentals.create'));
});

test('period stats count only completed sales and active rentals of the range', function () {
    $variant = createProductIn(null);

    sellVariant($variant, '2026-09-10 10:00:00', quantity: 2);
    sellVariant($variant, '2026-09-10 12:00:00', quantity: 1, status: 'cancelled');
    sellVariant($variant, '2026-09-11 12:00:00', quantity: 1);
    rentVariant($variant, '2026-09-10 09:00:00', status: 'out');
    rentVariant($variant, '2026-09-10 09:30:00', status: 'cancelled');
    OperationalExpense::create(['category' => 'Luz', 'concept' => 'Recibo', 'amount' => 30, 'expense_date' => '2026-09-10 08:00:00']);

    $day = CarbonImmutable::parse('2026-09-10');
    $stats = app(DashboardService::class)->periodStats($day->startOfDay(), $day->endOfDay());

    // sellVariant registra total 100 y subtotal 20×cantidad a costo 10 → margen 10×cantidad.
    expect($stats['sales_count'])->toBe(1)
        ->and($stats['sales_total'])->toEqual(100.0)
        ->and($stats['sales_margin'])->toEqual(20.0)
        ->and($stats['rentals_count'])->toBe(1)
        ->and($stats['income'])->toEqual(100.0)
        ->and($stats['expenses'])->toEqual(30.0);

    $month = app(DashboardService::class)->periodStats($day->startOfMonth(), $day->endOfMonth());

    expect($month['sales_count'])->toBe(2);
});

test('rankings and low stock alerts reflect the inventory', function () {
    $variant = createProductIn(null, stock: 1);
    createPhysicalUnit($variant);
    sellVariant($variant, '2026-09-10', quantity: 3);
    rentVariant($variant, '2026-09-12');

    $service = app(DashboardService::class);
    $from = CarbonImmutable::parse('2026-09-01');
    $to = $from->endOfMonth();

    expect((int) $service->topSold($from, $to)->first()->units)->toBe(3)
        ->and((int) $service->topRented($from, $to)->first()->units)->toBe(1)
        ->and($service->lowStock()->pluck('code'))->toContain($variant->product->code);
});

test('changing the date recalculates the day totals', function () {
    $variant = createProductIn(null);
    sellVariant($variant, '2026-03-15 11:00:00', quantity: 1);

    Livewire::test(Dashboard::class)
        ->set('date', '2026-03-15')
        ->assertSet('date', '2026-03-15')
        ->assertSee('15/03/2026')
        ->call('shiftDay', 1)
        ->assertSet('date', '2026-03-16')
        ->call('goToToday')
        ->assertSet('date', now()->toDateString());
});
