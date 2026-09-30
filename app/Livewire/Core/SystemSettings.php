<?php

namespace App\Livewire\Core;

use App\Models\DepreciationRule;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Configuración del sistema. Por ahora: desgaste de las prendas de alquiler
 * (desde N alquileres, su valor baja X %), usado en el valorizado.
 */
#[Title('Configuración')]
class SystemSettings extends Component
{
    /**
     * @var array<string, array{min_rentals: int|string, percent: float|string}>
     */
    public array $rules = [];

    public function mount(): void
    {
        $this->authorize('configuracion.general');

        foreach (DepreciationRule::orderBy('min_rentals')->get() as $rule) {
            $this->rules[(string) Str::uuid()] = [
                'min_rentals' => $rule->min_rentals,
                'percent' => (float) $rule->percent,
            ];
        }
    }

    public function addRule(): void
    {
        $last = collect($this->rules)->max('min_rentals') ?? 0;
        $this->rules[(string) Str::uuid()] = ['min_rentals' => (int) $last + 1, 'percent' => ''];
    }

    public function removeRule(string $key): void
    {
        unset($this->rules[$key]);
    }

    public function save(): void
    {
        $this->authorize('configuracion.general');

        $this->validate([
            'rules' => ['array'],
            'rules.*.min_rentals' => ['required', 'integer', 'min:1', 'max:1000', 'distinct'],
            'rules.*.percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ], [
            'rules.*.min_rentals.distinct' => 'Hay dos reglas con la misma cantidad de alquileres.',
        ], [
            'rules.*.min_rentals' => 'alquileres',
            'rules.*.percent' => 'porcentaje',
        ]);

        DB::transaction(function () {
            DepreciationRule::query()->delete();

            foreach ($this->rules as $rule) {
                DepreciationRule::create([
                    'min_rentals' => (int) $rule['min_rentals'],
                    'percent' => (float) $rule['percent'],
                ]);
            }
        });

        Flux::toast(variant: 'success', text: 'Configuración guardada.');
    }

    public function render(): View
    {
        return view('livewire.core.system-settings', [
            'sortedRules' => collect($this->rules)->sortBy(fn (array $rule) => (int) $rule['min_rentals']),
        ]);
    }
}
