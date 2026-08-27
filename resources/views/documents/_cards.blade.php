@php
    $hidden = array_merge($filters ?? [], ['view' => 'cards']);
    foreach ($selectedCols ?? [] as $colId) {
        $hidden['cols'][] = $colId;
    }
    $cardCount = count($cards ?? []);
@endphp
<div class="card p-3 mb-3">
    <form method="GET" action="{{ request()->url() }}" class="row g-2 align-items-center">
        <div class="col-auto">
            <label for="group-select" class="form-label small mb-0">{{ __('Regrouper par') }}</label>
        </div>
        <div class="col-auto">
            <select id="group-select" name="group" class="form-select form-select-sm" onchange="this.form.submit()">
                @foreach ($dimensions as $key => $label)
                    <option value="{{ $key }}" @selected($key === ($group ?? null))>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        @foreach ($hidden as $hKey => $hVal)
            @if (is_array($hVal))
                @foreach ($hVal as $hItem)
                    <input type="hidden" name="{{ $hKey }}[]" value="{{ $hItem }}">
                @endforeach
            @else
                <input type="hidden" name="{{ $hKey }}" value="{{ $hVal }}">
            @endif
        @endforeach
        <div class="col-auto ms-auto">
            <span class="small text-muted">{{ $cardCount }} {{ __('regroupement(s)') }}</span>
        </div>
    </form>
</div>

@if ($cardCount === 0)
    <div class="alert alert-info">{{ __('Aucun document ne correspond aux critères pour ce regroupement.') }}</div>
@else
    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-3">
        @foreach ($cards as $card)
            <div class="col">
                <a href="{{ request()->fullUrlWithQuery(['view' => 'list', 'group' => $group, 'value' => $card['value']]) }}"
                   class="card h-100 text-decoration-none shadow-sm">
                    <div class="card-body d-flex flex-column">
                        <h6 class="card-title mb-1" style="white-space: normal; overflow-wrap: anywhere" title="{{ $card['label'] }}">{{ $card['label'] }}</h6>
                        <div class="mt-auto d-flex justify-content-between align-items-center pt-2">
                            <span class="badge bg-primary rounded-pill">{{ $card['count'] }} {{ $card['count'] > 1 ? __('documents') : __('document') }}</span>
                            <i class="bi bi-arrow-right-circle text-muted"></i>
                        </div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>
@endif
