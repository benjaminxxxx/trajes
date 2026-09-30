<x-layouts::auth title="Iniciar sesión">
    <div class="flex flex-col gap-6">
        <div class="flex w-full flex-col text-center">
            <flux:heading size="xl" level="1">Bienvenido de nuevo</flux:heading>
            <flux:subheading>Ingresa con tu correo y contraseña para continuar.</flux:subheading>
        </div>

        <!-- Estado de la sesión (ej. contraseña restablecida) -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <x-passkey-verify />

        @include('partials.google-button')

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6">
            @csrf

            <!-- Correo -->
            <flux:input
                name="email"
                label="Correo electrónico"
                :value="old('email')"
                type="email"
                required
                autofocus
                autocomplete="email"
                icon="envelope"
                placeholder="tucorreo@ejemplo.com"
            />

            <!-- Contraseña -->
            <div class="relative">
                <flux:input
                    name="password"
                    label="Contraseña"
                    type="password"
                    required
                    autocomplete="current-password"
                    icon="lock-closed"
                    placeholder="Tu contraseña"
                    viewable
                />

                @if (Route::has('password.request'))
                    <flux:link class="absolute top-0 text-sm end-0" :href="route('password.request')" wire:navigate>
                        ¿Olvidaste tu contraseña?
                    </flux:link>
                @endif
            </div>

            <!-- Recordarme -->
            <flux:checkbox name="remember" label="Mantener la sesión iniciada" :checked="old('remember')" />

            <flux:button variant="primary" type="submit" class="w-full" icon-trailing="arrow-right" data-test="login-button">
                Iniciar sesión
            </flux:button>
        </form>

        @if (Route::has('register'))
            <div class="space-x-1 text-sm text-center rtl:space-x-reverse text-zinc-600 dark:text-zinc-400">
                <span>¿No tienes una cuenta?</span>
                <flux:link :href="route('register')" wire:navigate>Regístrate</flux:link>
            </div>
        @endif
    </div>
</x-layouts::auth>
