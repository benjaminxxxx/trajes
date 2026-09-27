<?php

namespace App\Livewire\Shared;

use App\Services\Shared\SearchResolver;
use Illuminate\Support\Collection;
use Livewire\Component;

class EntitySearchSelect extends Component
{
    public string $entityType;   // clave registrada en config('search.resolvers')
    public string $fieldContext; // identificador único: "purchase-supplier", "row-2-labor"
    public string $search = '';
    public ?int $selectedId = null;
    public ?string $selectedLabel = null;
    public ?string $selectedSubtitle = null;
    public bool $searching = true;

    // Si es true, seleccionar un resultado NO lo deja fijado en modo lectura:
    // solo dispara `entity-selected` y vuelve a dejar el buscador listo para
    // el siguiente término. Pensado para listas tipo "agregar producto",
    // donde se elige varias veces seguidas.
    public bool $resetAfterSelect = false;

    public function mount(
        string $entityType,
        string $fieldContext,
        ?int $selectedId = null,
        ?string $selectedLabel = null,
        ?string $selectedSubtitle = null,
        bool $resetAfterSelect = false,
    ): void {
        $this->entityType = $entityType;
        $this->fieldContext = $fieldContext;
        $this->selectedId = $selectedId;
        $this->selectedLabel = $selectedLabel;
        $this->selectedSubtitle = $selectedSubtitle;
        $this->resetAfterSelect = $resetAfterSelect;
        $this->searching = $resetAfterSelect || !$selectedId;
    }

    /**
     * El componente no sabe qué modelo hay detrás de "supplier" o "product":
     * solo resuelve la clase registrada en config('search.resolvers') y le
     * delega la búsqueda. Agregar una entidad nueva no requiere tocar esta
     * clase, solo registrar su resolver en config/search.php.
     */
    protected function resolver(): SearchResolver
    {
        $class = config("search.resolvers.{$this->entityType}");

        abort_unless($class, 404, "No hay resolver de búsqueda registrado para [{$this->entityType}].");

        return app($class);
    }

    public function getResultsProperty(): Collection
    {
        if (mb_strlen($this->search) < 2) {
            return collect();
        }

        return $this->resolver()->search($this->search);
    }

    public function select(int $id, string $label, ?string $subtitle = null, array $meta = []): void
    {
        $this->dispatch('entity-selected', context: $this->fieldContext, id: $id, label: $label, subtitle: $subtitle, meta: $meta);

        $this->search = '';

        if ($this->resetAfterSelect) {
            // Se queda buscando: no fijamos selectedId/selectedLabel.
            return;
        }

        $this->selectedId = $id;
        $this->selectedLabel = $label;
        $this->selectedSubtitle = $subtitle;
        $this->searching = false;
    }

    public function clear(): void
    {
        $this->reset(['selectedId', 'selectedLabel', 'selectedSubtitle', 'search']);
        $this->searching = true;

        $this->dispatch('entity-cleared', context: $this->fieldContext);
    }

    public function render()
    {
        return view('livewire.shared.entity-search-select');
    }
}