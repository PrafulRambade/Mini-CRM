@props(['column', 'label', 'sort', 'direction'])

@php
    $active = $sort === $column;
    $next = $active && $direction === 'asc' ? 'desc' : 'asc';
@endphp

<a href="{{ \App\Support\Listing::url(['sort' => $column, 'direction' => $next, 'page' => null]) }}"
   @if ($active) aria-sort="{{ $direction === 'asc' ? 'ascending' : 'descending' }}" @endif>
    {{ $label }}
    @if ($active)
        <i class="bi bi-arrow-{{ $direction === 'asc' ? 'up' : 'down' }}"></i>
    @else
        <i class="bi bi-arrow-down-up sort-idle"></i>
    @endif
</a>
