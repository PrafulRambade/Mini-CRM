@props(['status'])

<span {{ $attributes->class(['pill', 'pill-'.$status->value]) }}>{{ $status->label() }}</span>
