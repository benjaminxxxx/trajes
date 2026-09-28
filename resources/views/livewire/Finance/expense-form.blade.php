<div>
    <flux:modal wire:model="isOpen" class="max-w-xl">
        <div class="mb-5">
            <div class="mb-2 flex items-center gap-2 text-xs font-semibold text-violet-600 dark:text-violet-400">
                <span class="grid size-6 place-items-center rounded-lg bg-violet-100 dark:bg-violet-950/60">
                    <flux:icon icon="plus" class="size-3.5 text-violet-600 dark:text-violet-400" />
                </span>
                Nuevo registro
            </div>
            <h2 class="text-2xl font-semibold text-zinc-900 dark:text-white">Registrar gasto</h2>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                Escribe libremente; te sugerimos lo que ya usaste. Un gasto no se edita: si te equivocas, elimínalo y créalo de nuevo.
            </p>
        </div>

        <form wire:submit="save" class="flex flex-col gap-5">
            <div class="grid gap-4 sm:grid-cols-2">
                {{-- blur: al salir de "Categoría" se refrescan los conceptos sugeridos de esa categoría --}}
                <flux:input wire:model.blur="category" label="Categoría" list="expense-categories"
                    placeholder="Alquiler, Servicios básicos..." autocomplete="off" required />
                <flux:input wire:model="concept" label="Concepto" list="expense-concepts"
                    placeholder="Pago de luz, Pasaje a Lima..." autocomplete="off" required />
                <flux:input wire:model="amount" type="number" step="0.01" min="0" label="Monto (S/)" placeholder="0.00" required />
                <flux:input wire:model="expense_date" type="datetime-local" label="Fecha" required />
            </div>

            <datalist id="expense-categories">
                @foreach ($this->categories as $suggestion)
                    <option value="{{ $suggestion }}"></option>
                @endforeach
            </datalist>
            <datalist id="expense-concepts">
                @foreach ($this->concepts as $suggestion)
                    <option value="{{ $suggestion }}"></option>
                @endforeach
            </datalist>

            <flux:textarea wire:model="notes" label="Notas" rows="2"
                placeholder="Ej: Se fue a Gamarra a traer mercadería" />

            <div class="flex flex-col-reverse gap-3 border-t border-zinc-100 pt-5 dark:border-zinc-800 sm:flex-row sm:justify-end">
                <flux:modal.close>
                    <flux:button variant="subtle">Cancelar</flux:button>
                </flux:modal.close>

                <flux:button type="submit" variant="primary"
                    class="bg-zinc-950 text-white hover:bg-zinc-800 dark:bg-white dark:text-zinc-950 dark:hover:bg-zinc-200">
                    <span wire:loading.remove wire:target="save">Guardar gasto</span>
                    <span wire:loading wire:target="save">Guardando...</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>