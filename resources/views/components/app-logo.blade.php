@props([
    'sidebar' => false,
])

@if($sidebar)
    <flux:sidebar.brand name="Kipusoft Alquiler" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center">
            <x-brand.mark class="size-8" />
        </x-slot>
    </flux:sidebar.brand>
@else
    <a {{ $attributes->merge(['class' => 'me-4 flex items-center']) }} aria-label="Kipusoft Alquiler">
        <x-brand.logo size="sm" />
    </a>
@endif
