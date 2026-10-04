@props(['url'])
@php
    // "Kipusoft Alquiler" → la última palabra va en el color de la marca.
    $name = trim((string) $slot);
    $accent = \Illuminate\Support\Str::contains($name, ' ') ? \Illuminate\Support\Str::afterLast($name, ' ') : '';
    $base = $accent !== '' ? \Illuminate\Support\Str::beforeLast($name, ' ') : $name;
@endphp
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
{{ $base }}@if ($accent !== '') <span class="brand-accent">{{ $accent }}</span>@endif
</a>
</td>
</tr>
