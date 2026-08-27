@extends('layouts.app')
@section('title', 'Expenses')

@push('styles')
<style>
    .expense-dest-link { transition: background .12s ease; }
    .expense-dest-link:hover { background: var(--primary-light) !important; }
    .expense-filter-select { transition: border-color .2s; }

    /* ── Transaction cards ─────────────────────────────────── */
    .txn-card {
        display: flex; align-items: center; gap: 14px; padding: 14px 16px;
        border-bottom: 1px solid var(--border-light); transition: background .15s ease;
    }
    .txn-card:last-child { border-bottom: none; }
    .txn-card:hover { background: var(--bg); }
    .txn-icon { width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 15px; flex-shrink: 0; }
    .txn-main { flex: 1; min-width: 0; }
    .txn-desc { font-size: 14px; font-weight: 600; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .txn-meta { font-size: 12px; color: var(--muted); margin-top: 2px; display: flex; align-items: center; gap: 6px; }
    .txn-amount { font-size: 15px; font-weight: 700; color: var(--primary); flex-shrink: 0; font-variant-numeric: tabular-nums; }
    .txn-actions { display: flex; gap: 4px; flex-shrink: 0; }
    .txn-action-btn {
        width: 32px; height: 32px; border-radius: 8px; border: 1.5px solid var(--border); background: var(--bg-white);
        color: var(--muted); cursor: pointer; display: flex; align-items: center; justify-content: center;
        font-size: 12px; transition: transform .12s ease, border-color .15s ease, color .15s ease;
    }
    .txn-action-btn:hover { transform: translateY(-1px); border-color: var(--primary); color: var(--primary); }
    .txn-action-danger:hover { border-color: var(--danger); color: var(--danger); }
    .txn-icon-photo { padding: 0; overflow: hidden; }
    .txn-icon-photo img { width: 100%; height: 100%; object-fit: cover; display: block; }
    @media (max-width: 560px) {
        .txn-meta { flex-wrap: wrap; }
        .txn-desc { white-space: normal; }
    }

    /* ── Date group labels ────────────────────────────────── */
    .txn-date-label {
        font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase;
        color: var(--muted); padding: 14px 16px 6px;
    }
    .txn-date-label:first-child { padding-top: 16px; }

    /* ── Keyboard focus (WCAG 2.4.7) — every new interactive element
       gets a visible ring, since none of these existed before. ──── */
    .txn-action-btn:focus-visible,
    .txn-icon-photo:focus-visible,
    .expense-add-pill:focus-visible,
    .expense-dest-link:focus-visible {
        outline: 2px solid var(--primary); outline-offset: 2px;
    }

    /* ── Add Expense pill (replaced the floating + button) ── */
    .expense-add-pill {
        display: inline-flex; align-items: center; gap: 8px;
        background: var(--primary); color: #fff; border: none; border-radius: 30px;
        padding: 12px 26px; font-family: inherit; font-size: 13.5px; font-weight: 700;
        text-decoration: none; cursor: pointer; white-space: nowrap;
        transition: background .18s ease, gap .18s ease;
    }
    .expense-add-pill:hover { background: var(--primary-dark); color: #fff; gap: 11px; }
    .expense-add-pill i { font-size: 12px; }
    .expense-add-pill.is-compact { padding: 9px 18px; font-size: 12.5px; }

    /* ── From/To mini calendar dropdowns ──────────────────── */
    .exp-cal-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; font-size: 13px; font-weight: 700; color: var(--dark); }
    .exp-cal-nav { background: none; border: none; cursor: pointer; color: var(--muted); font-size: 14px; padding: 4px 8px; }
    .exp-cal-nav:hover { color: var(--primary); }
    .exp-cal-grid { display: grid; grid-template-columns: repeat(7,1fr); gap: 2px; text-align: center; }
    .exp-cal-day-name { font-size: 10px; font-weight: 700; color: var(--muted); padding: 4px 0; }
    .exp-cal-day { font-size: 12px; font-weight: 500; padding: 6px 4px; border-radius: 6px; cursor: pointer; color: var(--dark); }
    .exp-cal-day:hover:not(.past):not(.empty) { background: var(--bg); }
    .exp-cal-day.selected { background: var(--primary); color: #fff; }
    .exp-cal-day.empty { cursor: default; }
    .exp-cal-day.past { color: var(--muted); opacity: .4; cursor: not-allowed; }
</style>
@endpush

@section('content')

@if ($trips->isEmpty())
{{-- No trips empty state --}}
<div class="empty-state-center" style="min-height:80vh;">
    <div style="width:64px;height:64px;border-radius:16px;background:var(--primary);display:flex;align-items:center;justify-content:center;margin-bottom:24px;">
        <i class="fa-solid fa-receipt" style="font-size:28px;color:#fff;"></i>
    </div>
    @if (!auth()->user()?->userProfile)
    <h2 style="font-weight:700;font-size:22px;margin-bottom:10px;">Set up your profile first</h2>
    <p class="text-muted" style="margin-bottom:28px;font-size:14px;max-width:320px;line-height:1.6;">Complete your travel profile before planning a trip and logging expenses.</p>
    <a href="{{ route('profile.setup') }}" style="display:inline-flex;align-items:center;gap:10px;background:var(--primary);color:#fff;border-radius:30px;padding:14px 32px;font-size:13px;font-weight:700;letter-spacing:.06em;text-decoration:none;text-transform:uppercase;">
        <i class="fa-solid fa-user"></i> Set Up Your Profile First
    </a>
    @else
    <h2 style="font-weight:700;font-size:22px;margin-bottom:10px;">No expenses yet</h2>
    <p class="text-muted" style="margin-bottom:28px;font-size:14px;max-width:320px;line-height:1.6;">Plan a trip first before logging your expenses.</p>
    <a href="{{ route('trips.plan') }}" style="display:inline-flex;align-items:center;gap:10px;background:var(--primary);color:#fff;border-radius:30px;padding:14px 32px;font-size:13px;font-weight:700;letter-spacing:.06em;text-decoration:none;text-transform:uppercase;">
        <i class="fa-solid fa-plane"></i> Plan Your First Trip
    </a>
    @endif
</div>

@else
@php
    $selectedTripId = request('trip_id') ?? $trips->first()?->id;
    $selectedTrip   = $selectedTripId ? $trips->firstWhere('id', $selectedTripId) : null;
    $iataToCity = [
        'MNL'=>'Manila','CEB'=>'Cebu','IAO'=>'Siargao','PPS'=>'Puerto Princesa',
        'DVO'=>'Davao','ILO'=>'Iloilo','BCD'=>'Bacolod','TAG'=>'Tagbilaran',
        'GES'=>'General Santos','CBO'=>'Cotabato','ZAM'=>'Zamboanga',
        'KLO'=>'Kalibo','MPH'=>'Boracay','RXS'=>'Roxas','TAC'=>'Tacloban',
        'SIN'=>'Singapore','KUL'=>'Kuala Lumpur','BKK'=>'Bangkok','HKG'=>'Hong Kong',
        'NRT'=>'Tokyo','ICN'=>'Seoul','HND'=>'Tokyo','KIX'=>'Osaka',
        'SYD'=>'Sydney','MEL'=>'Melbourne','LAX'=>'Los Angeles','JFK'=>'New York',
        'DXB'=>'Dubai','CDG'=>'Paris','LHR'=>'London','FCO'=>'Rome',
        'BCN'=>'Barcelona','AMS'=>'Amsterdam','HAN'=>'Hanoi','SGN'=>'Ho Chi Minh',
        'DPS'=>'Bali','CGK'=>'Jakarta','MLE'=>'Maldives',
    ];
    $tripLabel = fn($t) => ($t->origin ?? 'Manila') . ' to ' . ($iataToCity[$t->destination_code ?? ''] ?? $t->destination);

    // Validated categorical palette (dataviz skill: 6 slots, fixed order,
    // CVD-safe). Used for the category icon badge on each transaction card.
    $categoryIcons = [
        'Transportation' => 'fa-plane', 'Accommodation' => 'fa-bed', 'Food' => 'fa-utensils',
        'Activities' => 'fa-camera-retro', 'Shopping' => 'fa-bag-shopping', 'Emergency Expenses' => 'fa-shield-halved',
    ];
    $categoryColors = [
        'Transportation'     => '#2a78d6',
        'Accommodation'      => '#eb6834',
        'Food'               => '#1baf7a',
        'Activities'         => '#eda100',
        'Shopping'           => '#e87ba4',
        'Emergency Expenses' => '#008300',
    ];

    // Preserve active filters when switching destination.
    $filterParams = request()->only(['category', 'date_from', 'date_to']);
@endphp

<div x-data="{ confirmDeleteId: null, confirmDeleteDesc: '' }" style="max-width:960px;margin:0 auto;width:100%;">

    {{-- Header: destination selector --}}
    <div style="margin-bottom:20px;">
        @if ($trips->count() === 1)
        @php $t = $trips->first(); @endphp
        <div style="background:var(--bg-white);border:1.5px solid var(--border);border-radius:16px;padding:12px 16px;display:flex;align-items:center;gap:12px;box-shadow:var(--shadow-sm);">
            <div style="width:34px;height:34px;border-radius:10px;background:var(--primary-light);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="fa-solid fa-plane" style="color:var(--primary);font-size:13px;"></i>
            </div>
            <span style="font-size:14px;font-weight:600;color:var(--dark);">{{ $tripLabel($t) }}</span>
        </div>
        @else
        <div x-data="{ open: false }" style="position:relative;">
            <button @click="open = !open" @click.away="open = false" type="button"
                    style="width:100%;background:var(--bg-white);border:1.5px solid var(--border);border-radius:16px;padding:12px 16px;display:flex;align-items:center;gap:12px;cursor:pointer;text-align:left;box-shadow:var(--shadow-sm);transition:border-color .15s;"
                    onmouseenter="this.style.borderColor='var(--primary)'" onmouseleave="this.style.borderColor='var(--border)'">
                <div style="width:34px;height:34px;border-radius:10px;background:var(--primary-light);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="fa-solid fa-plane" style="color:var(--primary);font-size:13px;"></i>
                </div>
                <span style="flex:1;font-size:14px;font-weight:600;color:var(--dark);">
                    @php $sel = $trips->firstWhere('id', $selectedTripId); @endphp
                    {{ $sel ? $tripLabel($sel) : 'Select a trip' }}
                </span>
                <i class="fa-solid fa-chevron-down" :style="'font-size:11px;color:var(--muted);transition:transform .2s;flex-shrink:0;' + (open ? 'transform:rotate(180deg)' : '')"></i>
            </button>
            <div x-show="open" x-transition
                 style="position:absolute;top:calc(100% + 8px);left:0;right:0;background:var(--bg-white);border:1.5px solid var(--border);border-radius:16px;box-shadow:0 12px 32px rgba(45,27,20,.14);z-index:50;overflow:hidden;padding:6px;">
                @foreach($trips as $t)
                <a href="{{ route('expenses.index') }}?{{ http_build_query(array_merge($filterParams, ['trip_id' => $t->id])) }}"
                   class="expense-dest-link"
                   style="display:flex;align-items:center;gap:11px;text-decoration:none;background:{{ $selectedTripId == $t->id ? 'var(--primary-light)' : 'transparent' }};border-radius:11px;padding:10px 12px;">
                    <div style="width:28px;height:28px;border-radius:8px;background:var(--bg);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i class="fa-solid fa-plane" style="color:var(--primary);font-size:11px;"></i>
                    </div>
                    <span style="font-size:13px;font-weight:{{ $selectedTripId == $t->id ? '600' : '400' }};color:{{ $selectedTripId == $t->id ? 'var(--primary)' : 'var(--muted)' }};">{{ $tripLabel($t) }}</span>
                </a>
                @endforeach
            </div>
        </div>
        @endif
    </div>

    {{-- Filters + Transactions — swapped in-place via AJAX so choosing a
         filter never triggers a full page reload (see ajaxFilterSubmit
         below); the whole region is re-fetched and its HTML spliced in. --}}
    <div id="expenses-region">

    {{-- Filters --}}
    <div class="card mb-16"><div class="card-body" style="padding:16px 20px;">
        <form method="GET" action="{{ route('expenses.index') }}" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
            <input type="hidden" name="trip_id" value="{{ $selectedTripId }}">
            <div style="flex:1;min-width:170px;position:relative;" x-data="{ catOpen:false }">
                <label class="form-label">Category</label>
                <input type="hidden" name="category" value="{{ request('category') }}">
                <div class="form-control expense-filter-select" style="display:flex;align-items:center;justify-content:space-between;cursor:pointer;" @click="catOpen=!catOpen">
                    <span style="font-weight:700;color:var(--dark);">{{ request('category') ?: 'All Categories' }}</span>
                    <i class="fa-solid fa-chevron-down" :style="'font-size:10px;color:var(--muted);flex-shrink:0;transition:transform .15s;' + (catOpen?'transform:rotate(180deg)':'')"></i>
                </div>
                <div x-show="catOpen" @click.outside="catOpen=false" x-cloak
                     style="position:absolute;top:calc(100% + 6px);left:0;right:0;background:var(--bg-white);border:1.5px solid var(--border);border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,.10);z-index:200;overflow:hidden;">
                    <button type="button" @click="catOpen=false" onclick="this.form.category.value='';ajaxFilterSubmit(this.form)"
                            style="width:100%;text-align:left;padding:11px 16px;border:none;background:none;font-size:13px;font-weight:700;cursor:pointer;{{ request('category') === null || request('category') === '' ? 'color:var(--primary);background:var(--primary-light);' : 'color:var(--dark);' }}">
                        All Categories
                    </button>
                    @foreach ($categories as $cat)
                    <button type="button" @click="catOpen=false" onclick="this.form.category.value='{{ $cat }}';ajaxFilterSubmit(this.form)"
                            style="width:100%;text-align:left;padding:11px 16px;border:none;background:none;font-size:13px;font-weight:700;cursor:pointer;{{ request('category') === $cat ? 'color:var(--primary);background:var(--primary-light);' : 'color:var(--dark);' }}">
                        {{ $cat }}
                    </button>
                    @endforeach
                </div>
            </div>
            <div style="display:contents;"
                 x-data='expenseDateFilter(@json(request("date_from") ?: ""), @json(request("date_to") ?: ""))'>
            <div style="flex:1;min-width:150px;position:relative;">
                <label class="form-label">From</label>
                <input type="hidden" name="date_from" :value="fromVal">
                <div class="form-control expense-filter-select" style="display:flex;align-items:center;justify-content:space-between;">
                    <div style="display:flex;align-items:center;gap:2px;font-weight:700;">
                        <input type="text" inputmode="numeric" placeholder="dd" maxlength="2" size="2"
                               x-ref="from_d" :value="fromParts.d"
                               @input="onDateSeg('from','d',$event)" @focus="activeCal='from'; $event.target.select()"
                               style="border:none;background:none;outline:none;padding:0;width:3ch;font-size:inherit;font-family:inherit;color:inherit;text-align:center;">
                        <span style="color:var(--muted);">/</span>
                        <input type="text" inputmode="numeric" placeholder="mm" maxlength="2" size="2"
                               x-ref="from_m" :value="fromParts.m"
                               @input="onDateSeg('from','m',$event)" @focus="activeCal='from'; $event.target.select()"
                               style="border:none;background:none;outline:none;padding:0;width:3ch;font-size:inherit;font-family:inherit;color:inherit;text-align:center;">
                        <span style="color:var(--muted);">/</span>
                        <input type="text" inputmode="numeric" placeholder="yyyy" maxlength="4" size="4"
                               x-ref="from_y" :value="fromParts.y"
                               @input="onDateSeg('from','y',$event)" @focus="activeCal='from'; $event.target.select()"
                               style="border:none;background:none;outline:none;padding:0;width:5ch;font-size:inherit;font-family:inherit;color:inherit;text-align:center;">
                    </div>
                    <i class="fa-regular fa-calendar" style="font-size:12px;color:var(--muted);flex-shrink:0;cursor:pointer;" @click="open('from')"></i>
                </div>
                <div x-show="activeCal==='from'" @click.outside="activeCal=''" @click.stop x-cloak
                     style="position:absolute;top:calc(100% + 6px);left:0;background:var(--bg-white);border:1.5px solid var(--border);border-radius:12px;box-shadow:0 8px 32px rgba(0,0,0,.14);z-index:200;padding:16px;min-width:260px;">
                    <div class="exp-cal-header">
                        <button type="button" class="exp-cal-nav" @click="prevMonth('from')"><i class="fa-solid fa-chevron-left"></i></button>
                        <span x-text="monthName(fromYear,fromMonth)+' '+fromYear"></span>
                        <button type="button" class="exp-cal-nav" @click="nextMonth('from')"><i class="fa-solid fa-chevron-right"></i></button>
                    </div>
                    <div class="exp-cal-grid">
                        <template x-for="d in ['Su','Mo','Tu','We','Th','Fr','Sa']"><div class="exp-cal-day-name" x-text="d"></div></template>
                        <template x-for="cell in fromCells" :key="cell.key">
                            <div class="exp-cal-day" :class="{'selected': cell.d && cell.val===fromVal, 'past': cell.disabled, 'empty': !cell.d}"
                                 @click.stop="cell.d && !cell.disabled && pick('from', cell.val)" x-text="cell.d||''"></div>
                        </template>
                    </div>
                </div>
            </div>
            <div style="flex:1;min-width:150px;position:relative;">
                <label class="form-label">To</label>
                <input type="hidden" name="date_to" :value="toVal">
                <div class="form-control expense-filter-select" style="display:flex;align-items:center;justify-content:space-between;">
                    <div style="display:flex;align-items:center;gap:2px;font-weight:700;">
                        <input type="text" inputmode="numeric" placeholder="dd" maxlength="2" size="2"
                               x-ref="to_d" :value="toParts.d"
                               @input="onDateSeg('to','d',$event)" @focus="activeCal='to'; $event.target.select()"
                               style="border:none;background:none;outline:none;padding:0;width:3ch;font-size:inherit;font-family:inherit;color:inherit;text-align:center;">
                        <span style="color:var(--muted);">/</span>
                        <input type="text" inputmode="numeric" placeholder="mm" maxlength="2" size="2"
                               x-ref="to_m" :value="toParts.m"
                               @input="onDateSeg('to','m',$event)" @focus="activeCal='to'; $event.target.select()"
                               style="border:none;background:none;outline:none;padding:0;width:3ch;font-size:inherit;font-family:inherit;color:inherit;text-align:center;">
                        <span style="color:var(--muted);">/</span>
                        <input type="text" inputmode="numeric" placeholder="yyyy" maxlength="4" size="4"
                               x-ref="to_y" :value="toParts.y"
                               @input="onDateSeg('to','y',$event)" @focus="activeCal='to'; $event.target.select()"
                               style="border:none;background:none;outline:none;padding:0;width:5ch;font-size:inherit;font-family:inherit;color:inherit;text-align:center;">
                    </div>
                    <i class="fa-regular fa-calendar" style="font-size:12px;color:var(--muted);flex-shrink:0;cursor:pointer;" @click="open('to')"></i>
                </div>
                <div x-show="activeCal==='to'" @click.outside="activeCal=''" @click.stop x-cloak
                     style="position:absolute;top:calc(100% + 6px);left:0;background:var(--bg-white);border:1.5px solid var(--border);border-radius:12px;box-shadow:0 8px 32px rgba(0,0,0,.14);z-index:200;padding:16px;min-width:260px;">
                    <div class="exp-cal-header">
                        <button type="button" class="exp-cal-nav" @click="prevMonth('to')"><i class="fa-solid fa-chevron-left"></i></button>
                        <span x-text="monthName(toYear,toMonth)+' '+toYear"></span>
                        <button type="button" class="exp-cal-nav" @click="nextMonth('to')"><i class="fa-solid fa-chevron-right"></i></button>
                    </div>
                    <div class="exp-cal-grid">
                        <template x-for="d in ['Su','Mo','Tu','We','Th','Fr','Sa']"><div class="exp-cal-day-name" x-text="d"></div></template>
                        <template x-for="cell in toCells" :key="cell.key">
                            <div class="exp-cal-day" :class="{'selected': cell.d && cell.val===toVal, 'past': cell.disabled, 'empty': !cell.d}"
                                 @click.stop="cell.d && !cell.disabled && pick('to', cell.val)" x-text="cell.d||''"></div>
                        </template>
                    </div>
                </div>
            </div>
            </div>
            @if (request('category') && request('date_from') && request('date_to'))
            <div style="flex-shrink:0;">
                <label class="form-label" style="visibility:hidden;">Clear</label>
                <button type="button" class="btn btn-outline btn-sm" style="padding:11px 16px;" onclick="ajaxFilterReset(this.form)">
                    <i class="fa-solid fa-xmark"></i> Clear
                </button>
            </div>
            @endif
        </form>
    </div></div>

    {{-- Transactions --}}
    <div class="card" style="overflow:hidden;box-sizing:border-box;display:flex;flex-direction:column;">
        @if (!$selectedTrip || $expenses->isEmpty())
        <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:48px 24px;text-align:center;">
            <div style="width:56px;height:56px;border-radius:16px;background:var(--primary-light);display:flex;align-items:center;justify-content:center;margin-bottom:16px;">
                <i class="fa-solid fa-receipt" style="font-size:24px;color:var(--primary);"></i>
            </div>
            <div style="font-size:15px;font-weight:600;margin-bottom:6px;">
                {{ (request('category') || request('date_from') || request('date_to')) ? 'No expenses match these filters' : 'No expenses recorded yet' }}
            </div>
            <div class="text-muted" style="font-size:13px;max-width:280px;line-height:1.5;">
                Start tracking your trip costs by adding your first expense. Keep your budget on track with real-time ledger updates.
            </div>
            <a href="{{ route('expenses.create') }}{{ $selectedTripId ? '?trip_id='.$selectedTripId : '' }}"
               class="expense-add-pill" style="margin-top:22px;">
                <i class="fa-solid fa-plus"></i> Add Expenses
            </a>
        </div>
        @else
        @php
            $groupedExpenses = $expenses->getCollection()->groupBy(function ($e) {
                if ($e->expense_date->isToday())     return 'Today';
                if ($e->expense_date->isYesterday()) return 'Yesterday';
                return $e->expense_date->format('F j, Y');
            });
        @endphp
        {{-- The floating + button used to be the ONLY route to expenses.create.
             With it gone, the empty state carries the pill and this row carries
             it once there are expenses to list. --}}
        <div style="display:flex;align-items:center;justify-content:flex-end;padding:12px 16px;border-bottom:1px solid var(--border-light);">
            <a href="{{ route('expenses.create') }}{{ $selectedTripId ? '?trip_id='.$selectedTripId : '' }}"
               class="expense-add-pill is-compact">
                <i class="fa-solid fa-plus"></i> Add Expenses
            </a>
        </div>
        <div>
            @foreach ($groupedExpenses as $groupLabel => $group)
            <div class="txn-date-label">{{ $groupLabel }}</div>
            @foreach ($group as $expense)
            @php $color = $categoryColors[$expense->category] ?? '#6B7280'; @endphp
            <div class="txn-card">
                {{-- A receipt_path can outlive its file (uploads from another
                     machine, a cleared storage dir). Rendering the <img>
                     regardless left a broken-image glyph in the row, so check
                     the file is actually there and fall back to the category
                     icon when it isn't. --}}
                @php
                    $hasReceipt = $expense->receipt_path
                        && Storage::disk('public')->exists($expense->receipt_path);
                @endphp
                @if ($hasReceipt)
                <a href="{{ Storage::url($expense->receipt_path) }}" target="_blank" rel="noopener" class="txn-icon txn-icon-photo" title="View receipt">
                    <img src="{{ Storage::url($expense->receipt_path) }}" alt="Receipt for {{ $expense->description ?? $expense->category }}" loading="lazy">
                </a>
                @else
                <div class="txn-icon" style="background:{{ $color }}22;color:{{ $color }};">
                    <i class="fa-solid {{ $categoryIcons[$expense->category] ?? 'fa-circle' }}"></i>
                </div>
                @endif
                @php $isMine = $expense->user_id === auth()->id(); @endphp
                <div class="txn-main">
                    <div class="txn-desc">{{ $expense->description ?? $expense->category }}</div>
                    <div class="txn-meta">
                        <span>{{ $expense->category }}</span> · <span>{{ $expense->expense_date->format('M j, Y') }}</span>
                        {{-- Only on a group trip's shared expenses — on your own
                             trips every row is yours and the name is noise. --}}
                        @unless ($isMine)
                        · <span style="color:var(--primary);font-weight:600;">{{ $expense->user->full_name ?? 'A fellow traveler' }}</span>
                        @endunless
                    </div>
                </div>
                <div class="txn-amount">{{ currency_symbol() }}{{ number_format($expense->amount, 0) }}</div>
                <div class="txn-actions">
                    {{-- Everyone on the trip can edit/delete its expenses; the
                         controller gates on trip access rather than authorship,
                         so these no longer dead-end on a companion's row. --}}
                    <a href="{{ route('expenses.edit', $expense) }}" class="txn-action-btn" title="Edit">
                        <i class="fa-solid fa-pen"></i>
                    </a>
                    <form id="expense-delete-{{ $expense->id }}" method="POST" action="{{ route('expenses.destroy', $expense) }}">
                        @csrf @method('DELETE')
                    </form>
                    <button type="button" class="txn-action-btn txn-action-danger" title="Delete"
                            @click="confirmDeleteId = {{ $expense->id }}; confirmDeleteDesc = @js($expense->description ?? $expense->category)">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </div>
            </div>
            @endforeach
            @endforeach
        </div>
        @if ($expenses->hasPages())
        <div style="padding:12px 16px;border-top:1px solid var(--border-light);">{{ $expenses->links() }}</div>
        @endif
        @endif
    </div>

    </div>{{-- /#expenses-region --}}

    {{-- Delete Confirmation Modal --}}
    <template x-if="confirmDeleteId">
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.55);backdrop-filter:blur(4px);z-index:2100;display:flex;align-items:center;justify-content:center;padding:20px;">
        <div style="background:var(--bg-white);border:1.5px solid var(--border);border-radius:20px;width:100%;max-width:360px;overflow:hidden;">
            <div style="background:var(--bg);padding:28px 24px 20px;text-align:center;">
                <div style="width:52px;height:52px;border-radius:50%;background:#FEE2E2;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;">
                    <i class="fa-solid fa-trash-can" style="font-size:22px;color:#DC2626;"></i>
                </div>
                <div style="font-size:17px;font-weight:700;color:var(--dark);margin-bottom:6px;">Delete Expense?</div>
                <div style="font-size:13px;color:var(--muted);line-height:1.5;">
                    <strong x-text="confirmDeleteDesc" style="color:var(--dark);"></strong> will be permanently deleted.<br>This action cannot be undone.
                </div>
            </div>
            <div style="display:flex;gap:10px;padding:18px 24px;">
                <button type="button" @click="confirmDeleteId = null" style="flex:1;padding:11px;border-radius:10px;border:1.5px solid var(--border);background:var(--bg-white);color:var(--dark);font-size:13px;font-weight:600;cursor:pointer;">Cancel</button>
                <button type="button" @click="document.getElementById('expense-delete-' + confirmDeleteId).submit()" style="flex:1;padding:11px;border-radius:10px;border:none;background:#DC2626;color:#fff;font-size:13px;font-weight:700;cursor:pointer;">Delete</button>
            </div>
        </div>
    </div>
    </template>

</div>

@endif

@push('scripts')
<script>
    // Swaps #expenses-region's HTML in place from a fetch of the same
    // route instead of a full page navigation, so choosing a category,
    // date range, or pagination page inside it never reloads the page.
    function ajaxNavigate(url) {
        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.text(); })
            .then(function (html) {
                var doc = new DOMParser().parseFromString(html, 'text/html');
                var newRegion = doc.getElementById('expenses-region');
                var oldRegion = document.getElementById('expenses-region');
                if (!newRegion || !oldRegion) { window.location.href = url; return; }
                oldRegion.replaceWith(newRegion);
                if (window.Alpine) { window.Alpine.initTree(newRegion); }
                window.history.pushState({}, '', url);
            });
    }

    function ajaxFilterSubmit(form) {
        var params = new URLSearchParams(new FormData(form));
        ajaxNavigate(form.action + '?' + params.toString());
    }

    function ajaxFilterReset(form) {
        form.category.value = '';
        form.date_from.value = '';
        form.date_to.value = '';
        ajaxFilterSubmit(form);
    }

    if (!window.__expensesRegionNavBound) {
        window.__expensesRegionNavBound = true;
        document.addEventListener('click', function (e) {
            var link = e.target.closest('#expenses-region a[href]');
            if (!link) return;
            var url = new URL(link.href, window.location.origin);
            if (url.pathname !== window.location.pathname) return;
            e.preventDefault();
            ajaxNavigate(link.href);
        });
    }

    // From/To mini calendars — the From calendar can't go past whatever
    // To is set to, and the To calendar can't go before From, enforced by
    // disabling (not just visually, but un-clickable) the out-of-range
    // days in each grid rather than only validating after the fact.
    window.expenseDateFilter = function (fromVal, toVal) {
        var months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
        var now = new Date();
        var seed = function (v) { return v ? new Date(v + 'T00:00:00') : now; };
        var fd = seed(fromVal), td = seed(toVal);

        return {
            activeCal: '',
            fromVal: fromVal || '',
            toVal: toVal || '',
            fromParts: { d: '', m: '', y: '' },
            toParts: { d: '', m: '', y: '' },
            fromYear: fd.getFullYear(), fromMonth: fd.getMonth() + 1,
            toYear: td.getFullYear(), toMonth: td.getMonth() + 1,
            fromCells: [], toCells: [],

            init() {
                this.rebuild();
                if (this.fromVal) { var fp = this.fromVal.split('-'); this.fromParts = { d: fp[2], m: fp[1], y: fp[0] }; }
                if (this.toVal) { var tp = this.toVal.split('-'); this.toParts = { d: tp[2], m: tp[1], y: tp[0] }; }
            },

            _buildCells(y, m, bound, boundIsMin) {
                var first = new Date(y, m - 1, 1).getDay();
                var days  = new Date(y, m, 0).getDate();
                var cells = [];
                for (var i = 0; i < first; i++) cells.push({ d: null, key: 'e'+y+m+i, val: '', disabled: false });
                for (var d = 1; d <= days; d++) {
                    var val = y + '-' + String(m).padStart(2,'0') + '-' + String(d).padStart(2,'0');
                    var disabled = bound ? (boundIsMin ? val < bound : val > bound) : false;
                    cells.push({ d: d, key: 'd'+y+m+d, val: val, disabled: disabled });
                }
                return cells;
            },

            rebuild() {
                this.fromCells = this._buildCells(this.fromYear, this.fromMonth, this.toVal || null, false);
                this.toCells   = this._buildCells(this.toYear,   this.toMonth,   this.fromVal || null, true);
            },

            open(which) { this.activeCal = this.activeCal === which ? '' : which; },

            prevMonth(which) {
                if (which === 'from') { this.fromMonth--; if (this.fromMonth < 1) { this.fromMonth = 12; this.fromYear--; } }
                else { this.toMonth--; if (this.toMonth < 1) { this.toMonth = 12; this.toYear--; } }
                this.rebuild();
            },
            nextMonth(which) {
                if (which === 'from') { this.fromMonth++; if (this.fromMonth > 12) { this.fromMonth = 1; this.fromYear++; } }
                else { this.toMonth++; if (this.toMonth > 12) { this.toMonth = 1; this.toYear++; } }
                this.rebuild();
            },

            monthName(y, m) { return months[m - 1]; },

            fmtLabel(val) {
                var parts = val.split('-');
                var d = parts[2].padStart(2, '0');
                var m = parts[1].padStart(2, '0');
                var y = parts[0];
                return d + '/' + m + '/' + y;
            },

            pick(which, val) {
                var vp = val.split('-');
                if (which === 'from') { this.fromVal = val; this.fromParts = { d: vp[2], m: vp[1], y: vp[0] }; }
                else { this.toVal = val; this.toParts = { d: vp[2], m: vp[1], y: vp[0] }; }
                this.rebuild();
                this.activeCal = '';
                var form = this.$el.closest('form');
                this.$nextTick(function () { ajaxFilterSubmit(form); });
            },

            onDateSeg(which, seg, e) {
                var max = seg === 'y' ? 4 : 2;
                var digits = e.target.value.replace(/\D/g, '').slice(0, max);
                if (seg === 'd' && digits.length === max && parseInt(digits, 10) > 31) digits = '31';
                if (seg === 'm' && digits.length === max && parseInt(digits, 10) > 12) digits = '12';
                if (seg === 'd' && digits.length === 1 && parseInt(digits, 10) > 3) { e.target.value = digits; var parts0 = which === 'from' ? this.fromParts : this.toParts; parts0.d = digits; this.$nextTick(() => this.$refs[which + '_m'] && this.$refs[which + '_m'].focus()); return; }
                if (seg === 'm' && digits.length === 1 && parseInt(digits, 10) > 1) { e.target.value = digits; var parts1 = which === 'from' ? this.fromParts : this.toParts; parts1.m = digits; this.$nextTick(() => this.$refs[which + '_y'] && this.$refs[which + '_y'].focus()); return; }
                e.target.value = digits;
                var parts = which === 'from' ? this.fromParts : this.toParts;
                parts[seg] = digits;

                if (digits.length >= max) {
                    var nextRef = seg === 'd' ? (which + '_m') : (seg === 'm' ? (which + '_y') : null);
                    if (nextRef) this.$nextTick(() => this.$refs[nextRef] && this.$refs[nextRef].focus());
                }

                var d = parseInt(parts.d, 10), m = parseInt(parts.m, 10), y = parseInt(parts.y, 10);
                if (!(parts.d.length === 2 && parts.m.length === 2 && parts.y.length === 4)) return;

                var dt = new Date(y, m - 1, d);
                var valid = m >= 1 && m <= 12 && d >= 1 && d <= 31 && y >= 2000 && y <= 2099
                    && dt.getFullYear() === y && dt.getMonth() === m - 1 && dt.getDate() === d;
                if (!valid) return;

                var val = y + '-' + String(m).padStart(2, '0') + '-' + String(d).padStart(2, '0');
                if (which === 'from' && this.toVal && val > this.toVal) return;
                if (which === 'to' && this.fromVal && val < this.fromVal) return;

                if (which === 'from') { this.fromVal = val; this.fromYear = y; this.fromMonth = m; }
                else { this.toVal = val; this.toYear = y; this.toMonth = m; }
                this.rebuild();
                this.activeCal = '';
                var form = this.$el.closest('form');
                this.$nextTick(function () { ajaxFilterSubmit(form); });
            },
        };
    };
</script>
@endpush

@endsection
