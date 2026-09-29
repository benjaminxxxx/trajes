@props([
    // 'light': texto oscuro (fondos claros). 'dark': texto blanco (fondos oscuros). 'auto': sigue el modo oscuro.
    'tone' => 'auto',
    'size' => 'md',
])

@php
    $markSize = ['sm' => 'size-8', 'md' => 'size-10', 'lg' => 'size-14'][$size] ?? 'size-10';
    $nameSize = ['sm' => 'text-base', 'md' => 'text-lg', 'lg' => 'text-2xl'][$size] ?? 'text-lg';
    $nameColor = ['light' => 'text-brand-navy', 'dark' => 'text-white', 'auto' => 'text-brand-navy dark:text-white'][$tone];
    $accentColor = ['light' => 'text-brand-600', 'dark' => 'text-brand-400', 'auto' => 'text-brand-600 dark:text-brand-400'][$tone];
@endphp

{{-- Logo horizontal de Kipusoft Alquiler con la tipografía de la app. --}}
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-3']) }}>
    <x-brand.mark class="{{ $markSize }} shrink-0" />
    <span class="h-8 w-0.5 rounded-full bg-brand-500/80"></span>
    <span class="flex flex-col leading-none">
        <span class="{{ $nameSize }} font-bold tracking-tight {{ $nameColor }}">Kipusoft<span class="{{ $accentColor }}">.pe</span></span>
        <span class="mt-1 text-[0.65rem] font-semibold uppercase tracking-[0.2em] {{ $accentColor }}">Alquiler</span>
    </span>
</span>
