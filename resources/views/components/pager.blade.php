@props(['paginator', 'label' => 'results'])

@if ($paginator->total() > 0)
    <div class="card-footer-pager">
        <div class="d-flex align-items-center gap-3 text-muted small">
            <span>
                Showing <strong class="text-2">{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }}</strong>
                of <strong class="text-2">{{ number_format($paginator->total()) }}</strong> {{ $label }}
            </span>
            <form method="GET" class="d-none d-md-flex align-items-center gap-2">
                @foreach (\App\Support\Listing::params(['per_page' => null, 'page' => null]) as $key => $value)
                    @if (is_string($value))
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endif
                @endforeach
                <label for="per_page" class="text-nowrap">Rows</label>
                <select id="per_page" name="per_page" class="form-select form-select-sm" data-autosubmit style="width:auto;min-height:32px">
                    @foreach ([10, 15, 25, 50, 100] as $size)
                        <option value="{{ $size }}" @selected($paginator->perPage() === $size)>{{ $size }}</option>
                    @endforeach
                </select>
            </form>
        </div>
        {{ $paginator->onEachSide(1)->links('partials.pagination') }}
    </div>
@endif
