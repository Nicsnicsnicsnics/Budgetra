<div>

    {{-- Scan Expense Banner --}}
    <div class="ocr-banner">
        <div class="ocr-banner-left">
            <div class="ocr-banner-icon"><i class="fa-solid fa-receipt"></i></div>
            <div>
                <h3>Track Your Spending</h3>
                <p>Scan a receipt to keep your budget up to date.</p>
            </div>
        </div>
        <a href="{{ route('expenses.create') }}?trip_id={{ $trip->id }}" class="btn btn-primary">
            <i class="fa-solid fa-camera"></i> Scan Expense
        </a>
    </div>

    {{-- Trip title --}}
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;" class="mb-16">
        <div>
            <h1>{{ $trip->destination }}</h1>
            <p class="text-muted">{{ $trip->start_date->format('M j') }} – {{ $trip->end_date->format('M j, Y') }} · {{ $this->days }} days</p>
        </div>
        <a href="{{ route('trips.index') }}" class="btn btn-outline btn-sm">← All Trips</a>
    </div>

    {{-- Stat cards --}}
    <div class="stats-row mb-24">
        <div class="stat-card">
            <div class="stat-card-accent" style="background:var(--primary);"></div>
            <div class="stat-label"><i class="fa-regular fa-calendar"></i> Total Budget</div>
            <div class="stat-value">{{ currency_symbol() }}{{ number_format($trip->budget_limit, 2) }}</div>
            <div class="stat-sub" style="color:var(--primary);">Allocated for {{ $this->days }} days</div>
            <div class="mt-8">
                <div class="progress"><div class="progress-bar" style="width:100%;background:var(--primary);"></div></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-accent" style="background:var(--secondary);"></div>
            <div class="stat-label"><i class="fa-regular fa-credit-card"></i> Amount Spent</div>
            <div class="stat-value" style="color:var(--secondary);">{{ currency_symbol() }}{{ number_format($this->totalSpent, 2) }}</div>
            <div class="stat-sub">{{ $this->spentPct }}% of total budget</div>
            <div class="mt-8">
                <div class="progress"><div class="progress-bar" style="width:{{ min(100,$this->spentPct) }}%;background:var(--secondary);"></div></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-accent" style="background:var(--tertiary);"></div>
            <div class="stat-label"><i class="fa-solid fa-building-columns"></i> Remaining Funds</div>
            <div class="stat-value" style="color:{{ $this->remaining < 0 ? 'var(--danger)' : 'var(--tertiary)' }};">
                {{ currency_symbol() }}{{ number_format(abs($this->remaining), 2) }}{{ $this->remaining < 0 ? ' over' : '' }}
            </div>
            <div class="stat-sub" style="color:{{ $this->remaining < 0 ? 'var(--danger)' : 'var(--tertiary)' }};">
                {{ $this->remaining >= 0 ? 'Stay on budget!' : 'You are over budget!' }}
            </div>
            <div class="mt-8">
                <div class="progress"><div class="progress-bar" style="width:{{ min(100,$this->spentPct) }}%;background:var(--tertiary);"></div></div>
            </div>
        </div>
    </div>

    {{-- Main content row --}}
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;" class="mb-24">

        {{-- Spending by Category — CSS bars --}}
        <div class="card card-body">
            <h3 class="mb-16">Spending by Category</h3>
            @php
                $catColors = [
                    'Transportation'      => ['bg'=>'#EFF6FF','bar'=>'#3B82F6','icon'=>'fa-plane'],
                    'Accommodation'       => ['bg'=>'#F0FDF4','bar'=>'#16A34A','icon'=>'fa-bed'],
                    'Food'                => ['bg'=>'#FFF7ED','bar'=>'#EA580C','icon'=>'fa-utensils'],
                    'Tourist Attractions' => ['bg'=>'#F5F3FF','bar'=>'#7C3AED','icon'=>'fa-landmark'],
                    'Shopping'            => ['bg'=>'#FDF2F8','bar'=>'#DB2777','icon'=>'fa-bag-shopping'],
                    'Emergency Funds'     => ['bg'=>'#FEF2F2','bar'=>'var(--danger)','icon'=>'fa-shield-halved'],
                ];
            @endphp

            @if ($this->budgetBreakdown->isEmpty())
            <div style="text-align:center;padding:32px 0;">
                <div style="font-size:36px;margin-bottom:10px;">📊</div>
                <p class="text-muted">No budget categories set.</p>
                <a href="{{ route('trips.budget', $trip) }}" style="color:var(--primary);font-size:13px;font-weight:600;">Set up budget →</a>
            </div>
            @else
            <div style="display:flex;flex-direction:column;gap:14px;">
                @foreach ($this->budgetBreakdown as $b)
                @php
                    $pct    = $b->estimated_cost > 0 ? min(100, round($b->actual_spent / $b->estimated_cost * 100)) : 0;
                    $over   = $b->actual_spent > $b->estimated_cost;
                    $cc     = $catColors[$b->category] ?? ['bg'=>'#F9FAFB','bar'=>'var(--primary)','icon'=>'fa-tag'];
                    $barClr = $over ? 'var(--danger)' : $cc['bar'];
                @endphp
                <div>
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
                        <div style="display:flex;align-items:center;gap:8px;">
                            <div style="width:28px;height:28px;border-radius:8px;background:{{ $cc['bg'] }};display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i class="fa-solid {{ $cc['icon'] }}" style="color:{{ $cc['bar'] }};font-size:12px;"></i>
                            </div>
                            <span style="font-size:13px;font-weight:600;color:var(--dark);">{{ $b->category }}</span>
                        </div>
                        <div style="text-align:right;">
                            <span style="font-size:12px;font-weight:700;color:{{ $over ? 'var(--danger)' : 'var(--dark)' }};">
                                {{ currency_symbol() }}{{ number_format($b->actual_spent, 0) }}
                            </span>
                            <span style="font-size:11px;color:var(--muted);"> / {{ currency_symbol() }}{{ number_format($b->estimated_cost, 0) }}</span>
                        </div>
                    </div>
                    <div style="height:7px;background:var(--border-light);border-radius:99px;overflow:hidden;">
                        <div style="height:100%;width:{{ $pct }}%;background:{{ $barClr }};border-radius:99px;transition:width 0.4s;"></div>
                    </div>
                    <div style="font-size:11px;color:{{ $over ? 'var(--danger)' : 'var(--muted)' }};margin-top:3px;text-align:right;">
                        {{ $pct }}% used{{ $over ? ' · Over budget!' : '' }}
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        </div>

        {{-- Recent Expenses --}}
        <div class="card card-body">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
                <h3>Recent Expenses</h3>
                <a href="{{ route('expenses.index') }}?trip_id={{ $trip->id }}" class="btn btn-outline btn-sm">View All</a>
            </div>

            @if ($this->recentExpenses->isEmpty())
            <div style="text-align:center;padding:32px 0;">
                <div style="font-size:36px;margin-bottom:10px;">🧾</div>
                <p class="text-muted" style="margin-bottom:10px;">No expenses yet.</p>
                <a href="{{ route('expenses.create') }}?trip_id={{ $trip->id }}" style="color:var(--primary);font-size:13px;font-weight:600;">Add your first one →</a>
            </div>
            @else
            <div style="display:flex;flex-direction:column;gap:10px;">
                @foreach ($this->recentExpenses as $exp)
                <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--border);">
                    <div>
                        <div style="font-size:13px;font-weight:600;color:var(--dark);">{{ $exp->description ?: $exp->category }}</div>
                        <div style="font-size:11px;color:var(--muted);margin-top:2px;">
                            {{ $exp->expense_date->format('M j') }}
                            &middot;
                            <span style="color:var(--primary);">{{ $exp->category }}</span>
                        </div>
                    </div>
                    <span style="font-size:14px;font-weight:700;color:var(--dark);">{{ currency_symbol() }}{{ number_format($exp->amount, 2) }}</span>
                </div>
                @endforeach
            </div>
            @endif
        </div>

    </div>

    {{-- Full-width Budget Breakdown table --}}
    <div class="card card-body mb-24">
        <h3 class="mb-16">Budget Breakdown</h3>
        @if ($this->budgetBreakdown->isEmpty())
        <p class="text-muted">No budget categories set. <a href="{{ route('trips.budget', $trip) }}" style="color:var(--primary);">Set up budget →</a></p>
        @else
        <table class="table">
            <thead>
                <tr>
                    <th>Category</th>
                    <th>Budgeted</th>
                    <th>Spent</th>
                    <th>Remaining</th>
                    <th>Usage</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($this->budgetBreakdown as $b)
                @php
                    $rem = $b->estimated_cost - $b->actual_spent;
                    $pct = $b->estimated_cost > 0 ? min(100, round($b->actual_spent / $b->estimated_cost * 100)) : 0;
                    $over = $rem < 0;
                    $cc   = $catColors[$b->category] ?? ['bg'=>'#F9FAFB','bar'=>'var(--primary)','icon'=>'fa-tag'];
                @endphp
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <div style="width:24px;height:24px;border-radius:6px;background:{{ $cc['bg'] }};display:flex;align-items:center;justify-content:center;">
                                <i class="fa-solid {{ $cc['icon'] }}" style="color:{{ $cc['bar'] }};font-size:10px;"></i>
                            </div>
                            {{ $b->category }}
                        </div>
                    </td>
                    <td>{{ currency_symbol() }}{{ number_format($b->estimated_cost, 2) }}</td>
                    <td>{{ currency_symbol() }}{{ number_format($b->actual_spent, 2) }}</td>
                    <td style="color:{{ $over ? 'var(--danger)' : 'inherit' }};font-weight:{{ $over ? '600' : '400' }};">
                        {{ $over ? '-' : '' }}{{ currency_symbol() }}{{ number_format(abs($rem), 2) }}
                    </td>
                    <td style="width:140px;">
                        <div style="display:flex;align-items:center;gap:8px;">
                            <div style="flex:1;height:6px;background:var(--border-light);border-radius:99px;overflow:hidden;">
                                <div style="height:100%;width:{{ $pct }}%;background:{{ $over ? 'var(--danger)' : $cc['bar'] }};border-radius:99px;"></div>
                            </div>
                            <span style="font-size:11px;font-weight:600;color:{{ $over ? 'var(--danger)' : 'var(--muted)' }};min-width:32px;">{{ $pct }}%</span>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>

</div>
