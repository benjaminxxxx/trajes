@if ($rental->isOverdue())
    <flux:badge size="sm" color="red">Vencido</flux:badge>
@else
    <flux:badge size="sm" :color="match ($rental->status) {
        \App\Models\Rental::STATUS_OUT => 'sky',
        \App\Models\Rental::STATUS_RESERVED => 'violet',
        \App\Models\Rental::STATUS_RETURNED => 'emerald',
        \App\Models\Rental::STATUS_CANCELLED => 'red',
        default => 'zinc',
    }">
        {{ \App\Models\Rental::STATUS_LABELS[$rental->status] ?? $rental->status }}
    </flux:badge>
@endif
