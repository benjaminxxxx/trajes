<?php

namespace App\Livewire\Core;

use App\Services\Core\DashboardService;
use App\Services\Inventory\ValuationService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Dashboard')]
class Dashboard extends Component
{
    public const MONTHS = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

    public const MONTH_NAMES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

    // Fecha consultada (Y-m-d). Los totales del día, del mes y del año se calculan a partir de ella.
    #[Url(as: 'fecha', history: true)]
    public string $date = '';

    public function mount(): void
    {
        $this->date = $this->selectedDate()->toDateString();
    }

    public function updatedDate(): void
    {
        $this->date = $this->selectedDate()->toDateString();
    }

    public function goToToday(): void
    {
        $this->date = now()->toDateString();
    }

    public function shiftDay(int $days): void
    {
        $this->date = $this->selectedDate()->addDays($days)->toDateString();
    }

    protected function selectedDate(): CarbonImmutable
    {
        try {
            return $this->date !== '' ? CarbonImmutable::parse($this->date) : CarbonImmutable::today();
        } catch (\Throwable) {
            return CarbonImmutable::today();
        }
    }

    #[Computed]
    public function day(): array
    {
        $date = $this->selectedDate();

        return app(DashboardService::class)->periodStats($date->startOfDay(), $date->endOfDay());
    }

    #[Computed]
    public function month(): array
    {
        $date = $this->selectedDate();

        return app(DashboardService::class)->periodStats($date->startOfMonth(), $date->endOfMonth());
    }

    /**
     * Valorizado actual de la empresa (no depende de la fecha consultada).
     *
     * @return array{cost: float, value: float}
     */
    #[Computed]
    public function companyValue(): array
    {
        return app(ValuationService::class)->summary();
    }

    #[Computed]
    public function yearSeries(): array
    {
        return app(DashboardService::class)->yearSeries($this->selectedDate()->year);
    }

    #[Computed]
    public function topSold()
    {
        $date = $this->selectedDate();

        return app(DashboardService::class)->topSold($date->startOfMonth(), $date->endOfMonth());
    }

    #[Computed]
    public function topRented()
    {
        $date = $this->selectedDate();

        return app(DashboardService::class)->topRented($date->startOfMonth(), $date->endOfMonth());
    }

    #[Computed]
    public function lowStock()
    {
        return app(DashboardService::class)->lowStock();
    }

    #[Computed]
    public function rentalsOut(): array
    {
        return app(DashboardService::class)->rentalsOut();
    }

    public function render(): View
    {
        $date = $this->selectedDate();

        return view('livewire.core.dashboard', [
            'months' => self::MONTHS,
            'monthLabel' => self::MONTH_NAMES[$date->month - 1].' '.$date->year,
            'dayLabel' => $date->isToday() ? 'Hoy' : $date->format('d/m/Y'),
        ]);
    }
}
