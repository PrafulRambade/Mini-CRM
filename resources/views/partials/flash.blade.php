@php
    $toasts = collect([
        'success' => ['icon' => 'bi-check-lg', 'tone' => 'success', 'title' => 'Success'],
        'status' => ['icon' => 'bi-info-lg', 'tone' => 'info', 'title' => 'Notice'],
        'error' => ['icon' => 'bi-x-lg', 'tone' => 'danger', 'title' => 'Error'],
    ])->filter(fn ($meta, $key) => session()->has($key));
@endphp

<div class="toast-container position-fixed top-0 end-0 p-3">
    @foreach ($toasts as $key => $meta)
        <div class="toast" role="{{ $key === 'error' ? 'alert' : 'status' }}" aria-live="{{ $key === 'error' ? 'assertive' : 'polite' }}" aria-atomic="true">
            <div class="toast-body">
                <span class="toast-icon tone-{{ $meta['tone'] }}"><i class="bi {{ $meta['icon'] }}"></i></span>
                <div class="flex-fill">
                    <div class="fw-semibold">{{ $meta['title'] }}</div>
                    <div class="text-2 small">{{ session($key) }}</div>
                </div>
                <button type="button" class="btn-close btn-sm" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-progress text-{{ $meta['tone'] }}"></div>
        </div>
    @endforeach
</div>
