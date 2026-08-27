@extends('layouts.admin')
@section('title', 'Admin Settings')

@section('content')
<style>
    .theme-swatch-grid { display: flex; flex-wrap: wrap; gap: 14px; }
    .theme-swatch {
        position: relative;
        width: 56px; height: 56px;
        border-radius: 16px;
        border: 2px solid var(--border);
        background: var(--bg-white);
        cursor: pointer;
        padding: 0;
        overflow: hidden;
        transition: transform 140ms ease, border-color 180ms ease, box-shadow 180ms ease;
    }
    .theme-swatch:hover { transform: translateY(-2px); }
    .theme-swatch[aria-checked="true"] { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-light); }

    .theme-swatch.split { display: flex; }
    .theme-swatch.split .swatch-half { width: 50%; height: 100%; }

    .theme-swatch-check {
        position: absolute; top: -6px; right: -6px;
        width: 18px; height: 18px; border-radius: 50%;
        background: var(--primary); color: #fff;
        display: flex; align-items: center; justify-content: center;
        opacity: 0; transform: scale(.6);
        transition: opacity 180ms ease, transform 180ms ease;
    }
    .theme-swatch[aria-checked="true"] .theme-swatch-check { opacity: 1; transform: scale(1); }
    .theme-swatch-check svg { width: 10px; height: 10px; stroke: currentColor; stroke-width: 3.5; fill: none; }

    .theme-swatch-caption { margin-top: 12px; font-size: 13px; color: var(--muted); }
    .theme-swatch-caption strong { color: var(--dark); font-weight: 600; }

    @media (prefers-reduced-motion: reduce) {
        .theme-swatch, .theme-swatch:hover { transition: none; transform: none; }
    }
</style>

<h1 style="font-size:26px;font-weight:800;margin:0 0 6px;color:var(--dark);">Settings</h1>
<p style="margin:0 0 28px;color:var(--muted);font-size:14px;">Manage your admin account and how the panel looks.</p>

@if (session('success'))
<div class="alert alert-success mb-16">{{ session('success') }}</div>
@endif

{{-- Account --}}
<div class="card" style="margin-bottom:20px;"><div class="card-body">
    <h2 style="font-size:15px;font-weight:700;margin:0 0 14px;color:var(--dark);">Account</h2>
    <div style="display:flex;align-items:center;gap:12px;">
        <div style="width:44px;height:44px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:15px;flex-shrink:0;">
            {{ collect(explode(' ', $user->full_name ?: 'Admin'))->map(fn($p) => mb_substr($p, 0, 1))->take(2)->implode('') }}
        </div>
        <div style="min-width:0;">
            <div style="font-size:14px;font-weight:700;color:var(--dark);">{{ $user->full_name }}</div>
            <div style="font-size:12.5px;color:var(--muted);">{{ $user->email }}</div>
        </div>
    </div>
</div></div>

{{-- Theme --}}
<div class="card"><div class="card-body">
    <h2 style="font-size:15px;font-weight:700;margin:0 0 14px;color:var(--dark);">Theme</h2>

    <div class="theme-swatch-grid" role="radiogroup" aria-label="Theme" id="themeGrid" data-current-theme="{{ $user->theme ?? 'daylight' }}">

        @foreach ([
            'original'          => 'Original — the classic Budgetra brown and cream',
            'daylight'          => 'Daylight — bright, sky-blue, easy on the eyes outdoors',
            'nightflight'       => 'Nightflight — deep navy, easy on the eyes at night',
            'terracotta'        => 'Terracotta Trail — warm clay and sand, adventure-trip feel',
            'retro-wanderlust'  => 'Retro Wanderlust — aged-paper cream and petrol teal, a vintage travel-poster feel',
            'sakura-bloom'      => 'Sakura Bloom — soft blossom pink and warm ink charcoal, delicate and seasonal',
        ] as $value => $label)
        <button class="theme-swatch" role="radio" title="{{ $label }}" aria-label="{{ $label }}"
                aria-checked="{{ ($user->theme ?? 'daylight') === $value ? 'true' : 'false' }}"
                data-theme-choice="{{ $value }}" data-theme="{{ $value }}" type="button">
            <span class="theme-swatch-check"><svg viewBox="0 0 24 24"><path d="M4 12l5 5L20 6"/></svg></span>
        </button>
        @endforeach

        <button class="theme-swatch split" role="radio" title="Auto — follows your device's light/dark setting" aria-label="Auto — follows your device's light/dark setting"
                aria-checked="{{ ($user->theme ?? 'daylight') === 'auto' ? 'true' : 'false' }}"
                data-theme-choice="auto" type="button">
            <span class="swatch-half" data-theme="daylight" style="background:var(--bg-white)"></span>
            <span class="swatch-half" data-theme="nightflight" style="background:var(--bg-white)"></span>
            <span class="theme-swatch-check"><svg viewBox="0 0 24 24"><path d="M4 12l5 5L20 6"/></svg></span>
        </button>

    </div>

    <div class="theme-swatch-caption" id="themeCaption"></div>
</div></div>

<script>
(function () {
    var grid = document.getElementById('themeGrid');
    var caption = document.getElementById('themeCaption');
    if (!grid) return;

    var cards = grid.querySelectorAll('[data-theme-choice]');
    var media = window.matchMedia('(prefers-color-scheme: dark)');
    var selection = grid.dataset.currentTheme || 'daylight';

    function resolve(choice) {
        if (choice !== 'auto') return choice;
        return media.matches ? 'nightflight' : 'daylight';
    }

    function reflect(choice) {
        document.body.setAttribute('data-theme', resolve(choice));
        cards.forEach(function (c) {
            c.setAttribute('aria-checked', String(c.dataset.themeChoice === choice));
        });
        var active = grid.querySelector('[data-theme-choice="' + choice + '"]');
        if (caption && active) caption.innerHTML = active.getAttribute('title');
    }

    function save(choice) {
        fetch('{{ route('settings.theme') }}', {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({ theme: choice }),
        });
    }

    reflect(selection);

    cards.forEach(function (card) {
        card.addEventListener('click', function () {
            selection = card.dataset.themeChoice;
            reflect(selection);
            save(selection);
        });
    });

    media.addEventListener('change', function () {
        if (selection === 'auto') reflect('auto');
    });
})();
</script>
@endsection
