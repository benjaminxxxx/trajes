{{-- Aviso (no bloqueante) de prenda separada. Espera $reservation (PhysicalProduct) y $clientId (cliente de la operación). --}}
@if ($reservation)
    @php($forSameClient = $clientId && (int) $clientId === (int) $reservation->reserved_client_id)
    <p @class([
        'mt-2 flex items-start gap-1.5 rounded-lg px-2 py-1.5 text-xs',
        'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-300' => $forSameClient,
        'bg-amber-50 text-amber-700 dark:bg-amber-950/30 dark:text-amber-300' => ! $forSameClient,
    ])>
        <flux:icon icon="bookmark" class="mt-px size-3.5 shrink-0" />
        <span>
            @if ($forSameClient)
                Separada para este cliente.
            @else
                Separada para <b>{{ $reservation->reservedClient?->display_name }}</b>
                hasta el {{ $reservation->reserved_until?->format('d/m/Y') }}{{ $reservation->isReservationExpired() ? ' (vencida)' : '' }}.
                Puedes continuar si el cliente llegó antes.
            @endif
        </span>
    </p>
@endif
