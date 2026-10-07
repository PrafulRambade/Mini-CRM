@props(['name' => '?', 'size' => null])

@php
    $initials = collect(preg_split('/\s+/', trim((string) $name)))
        ->filter()->take(2)
        ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))
        ->implode('') ?: '?';
    // Stable colour per name.
    $tone = 'avatar-c'.(crc32((string) $name) % 6);
@endphp

<span {{ $attributes->class(['avatar', $tone, $size ? 'avatar-'.$size : null]) }} aria-hidden="true">{{ $initials }}</span>
