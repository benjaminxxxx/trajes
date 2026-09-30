<div class="flex flex-col gap-6">
    <div class="text-center">
        <flux:heading size="xl">{{ $isCreating ? 'Registra tu negocio' : 'Elige tu negocio' }}</flux:heading>
        <flux:text class="mt-1">
            {{ $isCreating ? 'Con esto ya puedes empezar a registrar tus prendas, alquileres y ventas.' : 'Trabajas en más de un negocio: elige con cuál entrar.' }}
        </flux:text>
    </div>

    <x-auth-session-status class="text-center" :status="session('status')" />
    <flux:error name="account" />

    @unless ($isCreating)
        <div class="flex flex-col gap-2" data-test="account-list">
            @foreach ($memberships as $membership)
                @php($account = $membership->account)
                <button type="button" wire:key="account-{{ $account->id }}"
                    @if (! $account->isBlocked()) wire:click="enter({{ $account->id }})" @endif
                    @disabled($account->isBlocked())
                    @class([
                        'flex items-center gap-3 rounded-xl border p-3 text-left transition',
                        'border-zinc-200 hover:border-violet-400 dark:border-zinc-700' => ! $account->isBlocked(),
                        'cursor-not-allowed border-zinc-200 opacity-60 dark:border-zinc-800' => $account->isBlocked(),
                    ])>
                    <flux:avatar :name="$account->name" color="auto" size="sm" />
                    <span class="min-w-0 flex-1">
                        <span class="block truncate font-medium text-zinc-900 dark:text-white">{{ $account->name }}</span>
                        <span class="block text-xs text-zinc-500">
                            {{ $account->isOwnedBy(auth()->user()) ? 'Dueño' : 'Miembro' }}
                            @if ($account->ruc) · RUC {{ $account->ruc }} @endif
                        </span>
                    </span>
                    @if ($account->isBlocked())
                        <flux:badge size="sm" color="red">Bloqueado</flux:badge>
                    @elseif ($account->id === $currentAccountId)
                        <flux:badge size="sm" color="violet">Actual</flux:badge>
                    @endif
                </button>
            @endforeach
        </div>

        <flux:button variant="subtle" icon="plus" wire:click="$set('isCreating', true)">Registrar otro negocio</flux:button>
    @else
        <form wire:submit="create" class="flex flex-col gap-4">
            <flux:input wire:model="name" label="Nombre del negocio" placeholder="Alquiler de Trajes Elegancia" required autofocus />
            <flux:input wire:model="ruc" label="RUC (opcional)" placeholder="20123456789" maxlength="11" />
            <flux:button type="submit" variant="primary" class="w-full">Crear negocio</flux:button>
            @if ($memberships->isNotEmpty())
                <flux:button variant="subtle" wire:click="$set('isCreating', false)">Volver a mis negocios</flux:button>
            @endif
        </form>
    @endunless

    <form method="POST" action="{{ route('logout') }}" class="text-center">
        @csrf
        <flux:button type="submit" variant="ghost" size="sm">Cerrar sesión</flux:button>
    </form>
</div>
