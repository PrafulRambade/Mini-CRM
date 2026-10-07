@props(['title', 'subtitle' => null, 'breadcrumbs' => []])

<div class="page-header">
    <div class="min-w-0">
        @if ($breadcrumbs)
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="bi bi-house-door"></i></a></li>
                    @foreach ($breadcrumbs as $label => $url)
                        @if ($url)
                            <li class="breadcrumb-item"><a href="{{ $url }}">{{ $label }}</a></li>
                        @else
                            <li class="breadcrumb-item active" aria-current="page">{{ $label }}</li>
                        @endif
                    @endforeach
                </ol>
            </nav>
        @endif
        <h1 class="page-title text-truncate">{{ $title }}</h1>
        @if ($subtitle)
            <p class="page-subtitle">{{ $subtitle }}</p>
        @endif
    </div>
    @if (isset($actions))
        <div class="d-flex flex-wrap gap-2">{{ $actions }}</div>
    @endif
</div>
