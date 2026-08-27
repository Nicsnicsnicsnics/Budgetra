@extends('layouts.app')
@section('title', 'Savings Goals')

@push('styles')
<style>
    /* The layout lives in a class, not an inline style, because x-show
       un-hides by calling style.removeProperty('display') — which strips an
       inline display:flex along with the display:none it set, leaving the
       block laid out as plain flow (icon hard left, heading centred only by
       text-align, paragraph adrift). A class rule survives that. */
    .sg-empty {
        min-height: 360px; display: flex; flex-direction: column;
        align-items: center; justify-content: center;
        text-align: center; padding: 40px 20px;
    }
</style>
@endpush

@section('content')


@if (session('success'))
<div class="alert alert-success mb-16">{{ session('success') }}</div>
@endif

@if ($goals->isEmpty())
<div class="empty-state-center" style="min-height:80vh;">
    <div style="width:64px;height:64px;border-radius:18px;background:var(--primary);display:flex;align-items:center;justify-content:center;margin-bottom:24px;">
        <i class="fa-solid fa-piggy-bank" style="font-size:28px;color:#fff;"></i>
    </div>
    @if (!auth()->user()?->userProfile)
    <h2 style="font-weight:700;font-size:22px;margin-bottom:10px;color:var(--dark);">Set up your profile first</h2>
    <p style="color:var(--muted);margin-bottom:28px;font-size:14px;max-width:320px;line-height:1.6;">Complete your travel profile before planning a trip and setting trip goals.</p>
    <a href="{{ route('profile.setup') }}" style="display:inline-flex;align-items:center;gap:10px;background:var(--primary);color:#fff;border-radius:30px;padding:14px 32px;font-size:13px;font-weight:700;letter-spacing:.06em;text-decoration:none;text-transform:uppercase;transition:background .18s;"
       onmouseenter="this.style.background='var(--primary-dark)'" onmouseleave="this.style.background='var(--primary)'">
        <i class="fa-solid fa-user"></i> Set Up Your Profile First
    </a>
    @else
    <h2 style="font-weight:700;font-size:22px;margin-bottom:10px;color:var(--dark);">No savings goals yet</h2>
    <p style="color:var(--muted);margin-bottom:28px;font-size:14px;max-width:320px;line-height:1.6;">Plan a trip first before adding your savings goals for your destinations.</p>
    <a href="{{ route('trips.plan') }}" style="display:inline-flex;align-items:center;gap:10px;background:var(--primary);color:#fff;border-radius:30px;padding:14px 32px;font-size:13px;font-weight:700;letter-spacing:.06em;text-decoration:none;text-transform:uppercase;transition:background .18s;"
       onmouseenter="this.style.background='var(--primary-dark)'" onmouseleave="this.style.background='var(--primary)'">
        <i class="fa-solid fa-plane"></i> Plan Your First Trip
    </a>
    @endif
</div>
@else
@php
    $isDraftGoal = function ($goal) {
        $trip = $goal->trip;
        return $trip && ($trip->status ?? null) === 'draft';
    };
    $isPastGoal = function ($goal) {
        $trip = $goal->trip;
        if (!$trip) return false;
        return ($trip->status ?? null) === 'past'
            || ($trip->end_date && $trip->end_date->lt(\Carbon\Carbon::today()));
    };
    $pastGoals   = $goals->filter(fn($g) => !$isDraftGoal($g) && $isPastGoal($g));
    $activeGoals = $goals->reject(fn($g) => $isDraftGoal($g) || $isPastGoal($g));
    $sgGroups = [
        ['key' => 'active', 'label' => 'Active Goals', 'icon' => 'fa-solid fa-piggy-bank',        'items' => $activeGoals, 'noun' => 'active goals'],
        ['key' => 'past',   'label' => 'Past Goals',   'icon' => 'fa-solid fa-clock-rotate-left', 'items' => $pastGoals,   'noun' => 'past goals'],
    ];

    // One lowercased haystack per goal — its own name plus the trip's
    // destination and name — so a goal is findable by any of them.
    $sgHay = fn ($g) => mb_strtolower(trim(
        ($g->goal_name ?? '') . ' ' . ($g->trip->destination ?? '') . ' ' . ($g->trip->trip_name ?? '')
    ));
    $sgIndex = collect($sgGroups)->mapWithKeys(fn ($g) => [
        $g['key'] => $g['items']->map($sgHay)->values()->all(),
    ])->all();
@endphp

{{-- Filtering runs in Alpine rather than on the server: this page is plain
     Blade whose cards each mount their own Livewire component, so there is no
     wire:model to bind to, and every goal is already on the page. --}}
<div x-data="{
        tab: 'active',
        q: '',
        get needle() { return this.q.trim().toLowerCase(); },
        matches(hay) { return this.needle === '' || hay.includes(this.needle); },
        countFor(key) { return this.index[key].filter(h => this.matches(h)).length; },
        index: @js($sgIndex),
     }" style="display:flex;flex-direction:column;">
    {{-- Browser-tab-style switcher --}}
    <div style="display:flex;align-items:flex-end;gap:4px;margin-bottom:0;flex-shrink:0;flex-wrap:wrap;">
        @foreach ($sgGroups as $sgGroup)
        @php $sgCount = $sgGroup['items']->count(); @endphp
        <button @click="tab = '{{ $sgGroup['key'] }}'" type="button"
                :style="'display:flex;align-items:center;gap:10px;padding:12px 22px;border:1.5px solid;border-bottom:none;border-radius:18px 18px 0 0;cursor:pointer;font-family:inherit;position:relative;white-space:nowrap;flex-wrap:nowrap;transition:background .15s ease,color .15s ease;' + (tab === '{{ $sgGroup['key'] }}' ? 'background:var(--bg-white);border-color:var(--border);color:var(--dark);z-index:2;margin-bottom:-1.5px;' : 'background:var(--bg);border-color:transparent;color:var(--muted);z-index:1;')">
            <div style="width:26px;height:26px;border-radius:8px;background:var(--primary);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="{{ $sgGroup['icon'] }}" style="color:#fff;font-size:11px;"></i>
            </div>
            <span style="font-size:14px;font-weight:700;">{{ $sgGroup['label'] }}</span>
            <span x-text="countFor('{{ $sgGroup['key'] }}')"
                  :style="'font-size:11px;font-weight:800;border-radius:99px;min-width:20px;height:20px;padding:0 6px;display:inline-flex;align-items:center;justify-content:center;line-height:1;' + (countFor('{{ $sgGroup['key'] }}') > 0 ? 'color:#fff;background:var(--primary);' : 'color:var(--muted);background:var(--bg);')"></span>
        </button>
        @endforeach

        <label class="page-search">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" x-model="q" placeholder="Search goals">
            <button type="button" x-show="q" x-cloak @click="q = ''" title="Clear search">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </label>
    </div>

    {{-- Tab panels --}}
    <div style="background:var(--bg-white);border:1.5px solid var(--border);border-radius:0 16px 16px 16px;padding:24px;display:flex;flex-direction:column;position:relative;">
        @foreach ($sgGroups as $sgGroup)
        <div x-show="tab === '{{ $sgGroup['key'] }}'" x-cloak style="display:flex;flex-direction:column;">
            @if ($sgGroup['items']->isEmpty())
            {{-- Searching an already-empty tab still needs the search wording:
                 "No past goals yet" would answer a question the traveler
                 didn't ask and point them at planning a trip instead. --}}
            <div class="sg-empty">
                <div style="width:56px;height:56px;border-radius:16px;background:var(--primary);display:flex;align-items:center;justify-content:center;margin-bottom:18px;">
                    <i class="{{ $sgGroup['icon'] }}" x-show="q.trim() === ''" style="font-size:24px;color:#fff;"></i>
                    <i class="fa-solid fa-magnifying-glass" x-show="q.trim() !== ''" x-cloak style="font-size:22px;color:#fff;"></i>
                </div>
                <h3 x-show="q.trim() === ''" style="font-weight:700;font-size:17px;margin:0 0 6px;color:var(--dark);">No {{ $sgGroup['label'] }} yet</h3>
                <p x-show="q.trim() === ''" style="color:var(--muted);font-size:13px;max-width:280px;line-height:1.6;margin:0;">Plan a trip first to see your {{ $sgGroup['noun'] }}.</p>
                <h3 x-show="q.trim() !== ''" x-cloak style="font-weight:700;font-size:17px;margin:0 0 6px;color:var(--dark);">No goals found</h3>
                <p x-show="q.trim() !== ''" x-cloak style="color:var(--muted);font-size:13px;max-width:280px;line-height:1.6;margin:0;">Try searching another goal.</p>
            </div>
            @else
            <div style="width:100%;display:flex;flex-wrap:wrap;justify-content:center;gap:20px;align-items:flex-start;">
                @foreach ($sgGroup['items'] as $goal)
                {{-- x-show, not a wrapper that unmounts the card: the goal
                     card is a Livewire component, and tearing it out of the
                     DOM on every keystroke would remount it (and drop any
                     open deposit modal) instead of just hiding it. --}}
                <div x-show="matches(@js($sgHay($goal)))" style="flex:1 1 360px;max-width:420px;">
                    @livewire('traveler.savings-goal-manager', ['goal' => $goal], key($goal->id))
                </div>
                @endforeach
            </div>
            {{-- Shown only while a search is filtering everything out. --}}
            <div x-show="q.trim() !== '' && countFor('{{ $sgGroup['key'] }}') === 0" x-cloak class="sg-empty">
                <div style="width:56px;height:56px;border-radius:16px;background:var(--primary);display:flex;align-items:center;justify-content:center;margin-bottom:18px;">
                    <i class="fa-solid fa-magnifying-glass" style="font-size:22px;color:#fff;"></i>
                </div>
                <h3 style="font-weight:700;font-size:17px;margin:0 0 6px;color:var(--dark);">No goals found</h3>
                <p style="color:var(--muted);font-size:13px;max-width:280px;line-height:1.6;margin:0;">Try searching another goal.</p>
            </div>
            @endif
        </div>
        @endforeach
    </div>
</div>
@endif

@endsection
