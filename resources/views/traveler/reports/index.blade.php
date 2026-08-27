@extends('layouts.app')
@section('title', 'Budget Reports')
@section('content')
<h1>Budget Reports</h1>
@forelse($trips as $trip)
    @php $s = $summaries[$trip->id]; @endphp
    <div class="card" style="margin-bottom:1rem;">
        <div class="card-body">
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <div>
                    <h3>{{ $trip->destination }}</h3>
                    <p>{{ $trip->start_date }} &rarr; {{ $trip->end_date }}</p>
                    <p>
                        Estimated: <strong>&#8369;{{ number_format($s['total_estimated'], 2) }}</strong> &bull;
                        Spent: <strong>&#8369;{{ number_format($s['total_spent'], 2) }}</strong> &bull;
                        Remaining: <strong style="{{ $s['remaining'] < 0 ? 'color:red' : 'color:green' }}">&#8369;{{ number_format($s['remaining'], 2) }}</strong>
                    </p>
                </div>
                <a href="{{ route('reports.download') }}?trip_id={{ $trip->id }}" class="btn btn-primary">
                    Download PDF
                </a>
            </div>
            @if(count($s['categories']))
                <div style="margin-top:1rem;">
                    @foreach($s['categories'] as $cat)
                        @php $pct = $cat['estimated_cost'] > 0 ? min(100, ($cat['actual_spent'] / $cat['estimated_cost']) * 100) : 0; @endphp
                        <div style="margin-bottom:8px;">
                            <div style="display:flex;justify-content:space-between;font-size:.85rem;">
                                <span>{{ $cat['category'] }}</span>
                                <span>&#8369;{{ number_format($cat['actual_spent'], 0) }} / &#8369;{{ number_format($cat['estimated_cost'], 0) }}</span>
                            </div>
                            <div style="height:8px;background:#eee;border-radius:4px;">
                                <div style="height:100%;width:{{ $pct }}%;background:{{ $pct >= 100 ? '#e74c3c' : ($pct >= 80 ? '#f39c12' : '#2ecc71') }};border-radius:4px;"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@empty
    <p>No trips to report on yet.</p>
@endforelse
@endsection
