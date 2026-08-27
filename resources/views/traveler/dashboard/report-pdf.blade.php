<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    body { font-family: Arial, sans-serif; font-size: 12px; color: #333; }
    h1 { color: #2c3e50; margin: 0 0 4px; }
    h3 { color: #2c3e50; margin: 24px 0 8px; }
    table { width: 100%; border-collapse: collapse; margin-top: 8px; }
    th { background: #2c3e50; color: white; padding: 8px; text-align: left; }
    td { padding: 6px 8px; border-bottom: 1px solid #eee; }
    .totals td { font-weight: bold; background: #f8f9fa; }
    .header { border-bottom: 2px solid #2c3e50; padding-bottom: 12px; margin-bottom: 16px; }
    .stats-row { width: 100%; margin-top: 12px; }
    .stats-row td { border: 1px solid #eee; padding: 12px 16px; width: 33.33%; }
    .stats-label { font-size: 10px; text-transform: uppercase; color: #888; letter-spacing: .04em; }
    .stats-value { font-size: 20px; font-weight: bold; color: #2c3e50; margin-top: 4px; }
    .bar-track { background: #eee; border-radius: 6px; height: 8px; overflow: hidden; }
    .bar-fill { background: #6C63FF; height: 8px; }
</style>
</head>
<body>
<div class="header">
    <h1>Dashboard Report</h1>
    <p>{{ auth()->user()->full_name ?? '' }} &bull; Generated: {{ $generatedAt->format('F j, Y g:i A') }}</p>
</div>

<table class="stats-row">
    <tr>
        <td>
            <div class="stats-label">Total Trips</div>
            <div class="stats-value">{{ $trips->count() }}</div>
        </td>
        <td>
            <div class="stats-label">Total Budget</div>
            <div class="stats-value">PHP {{ number_format($totalBudget, 2) }}</div>
        </td>
        <td>
            <div class="stats-label">Total Spent</div>
            <div class="stats-value">PHP {{ number_format($totalSpent, 2) }}</div>
        </td>
    </tr>
</table>

<h3>Spending by Category</h3>
<table>
    <thead><tr><th>Category</th><th>Amount</th><th>% of Total</th></tr></thead>
    <tbody>
    @forelse ($categorySpend as $cat)
        <tr>
            <td>{{ $cat['label'] }}</td>
            <td>PHP {{ number_format($cat['value'], 2) }}</td>
            <td>{{ $totalSpent > 0 ? round($cat['value'] / $totalSpent * 100) : 0 }}%</td>
        </tr>
    @empty
        <tr><td colspan="3">No expenses logged yet.</td></tr>
    @endforelse
    </tbody>
</table>

<h3>Monthly Spending (Last 6 Months)</h3>
<table>
    <thead><tr><th>Month</th><th>Amount</th></tr></thead>
    <tbody>
    @foreach ($monthlySpend as $m)
        <tr>
            <td>{{ $m['label'] }}</td>
            <td>PHP {{ number_format($m['value'], 2) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<h3>Budget Usage by Trip</h3>
<table>
    <thead><tr><th>Trip</th><th>Status</th><th>Spent</th><th>Budget</th><th>Usage</th></tr></thead>
    <tbody>
    @forelse ($trips as $trip)
        <tr>
            <td>{{ $trip->destination }}</td>
            <td>{{ ucfirst($trip->status) }}</td>
            <td>PHP {{ number_format($trip->total_spent, 2) }}</td>
            <td>PHP {{ number_format($trip->budget_limit, 2) }}</td>
            <td>
                {{ $trip->pct_used }}%
                <div class="bar-track"><div class="bar-fill" style="width:{{ min(100, $trip->pct_used) }}%;"></div></div>
            </td>
        </tr>
    @empty
        <tr><td colspan="5">No trips yet.</td></tr>
    @endforelse
    </tbody>
</table>
</body>
</html>
