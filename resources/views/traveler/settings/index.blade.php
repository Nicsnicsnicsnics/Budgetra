@extends('layouts.app')
@section('title', 'Settings')

@push('styles')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Sora:wght@600;700&display=swap" rel="stylesheet">
<style>
    /* Theme colors themselves (daylight = :root default, plus
       [data-theme="nightflight|terracotta|retro-wanderlust|sakura-bloom"])
       live globally in style.css so this page's own background/sidebar/etc.
       — not just the swatches below — reskin along with the rest of the app. */
    .appearance-page {
        --space-3: 12px; --space-4: 16px; --space-5: 24px; --space-6: 32px;
        --font-display: 'Sora', system-ui, sans-serif;
        --font-body: 'Inter', system-ui, sans-serif;
        --ease: 180ms cubic-bezier(.4,0,.2,1);
        font-family: var(--font-body);
    }
    .appearance-page h1, .appearance-page .section-label { font-family: var(--font-display); }

    .appearance-page .page-head { margin-bottom: var(--space-6); }
    .appearance-page .page-head h1 { margin: 0 0 4px; font-size: 22px; color: var(--dark); }
    .appearance-page .page-head p { margin: 0; color: var(--muted); font-size: 14px; }

    .appearance-page .section-label {
        font-size: 13px; font-weight: 600; color: var(--muted);
        text-transform: uppercase; letter-spacing: .04em; margin-bottom: var(--space-3);
    }

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
        transition: transform 140ms ease, border-color var(--ease), box-shadow var(--ease);
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
        transition: opacity var(--ease), transform var(--ease);
    }
    .theme-swatch[aria-checked="true"] .theme-swatch-check { opacity: 1; transform: scale(1); }
    .theme-swatch-check svg { width: 10px; height: 10px; stroke: currentColor; stroke-width: 3.5; fill: none; }

    .theme-swatch-caption { margin-top: var(--space-3); font-size: 13px; color: var(--muted); }
    .theme-swatch-caption strong { color: var(--dark); font-weight: 600; }

    @media (prefers-reduced-motion: reduce) {
        .theme-swatch, .theme-swatch:hover { transition: none; transform: none; }
    }

    /* ── Account / Preferences / Notifications / Receipt scanning ── */
    .settings-card { margin-bottom: 20px; }
    .settings-card:last-child { margin-bottom: 0; }
    .settings-card h2 {
        font-family: var(--font-display); font-size: 15px; font-weight: 700;
        color: var(--dark); margin: 0 0 16px;
    }
    .settings-row {
        display: flex; align-items: center; justify-content: space-between; gap: 16px;
        padding: 14px 0; border-top: 1px solid var(--border-light);
    }
    .settings-row:first-of-type { border-top: none; padding-top: 0; }
    .settings-avatar {
        width: 44px; height: 44px; border-radius: 50%; background: var(--primary-light);
        color: var(--primary); display: flex; align-items: center; justify-content: center;
        font-weight: 700; font-size: 15px; flex-shrink: 0; overflow: hidden;
    }
    .settings-avatar img { width: 100%; height: 100%; object-fit: cover; }
    .settings-label { font-size: 14px; font-weight: 600; color: var(--dark); }
    .settings-sub { font-size: 12px; color: var(--muted); margin-top: 2px; }
    .settings-btn {
        background: var(--bg-white); border: 1.5px solid var(--border); color: var(--dark);
        border-radius: 10px; padding: 9px 16px; font-size: 13px; font-weight: 600;
        cursor: pointer; white-space: nowrap; text-decoration: none; display: inline-block;
        transition: border-color .15s ease;
    }
    .settings-btn:hover { border-color: var(--primary); }

    .settings-select {
        width: 100%; background: var(--bg-white); border: 1.5px solid var(--border);
        border-radius: 10px; padding: 10px 12px; font-size: 14px; font-family: inherit;
        color: var(--dark); cursor: pointer;
    }
    .settings-field-label { display: block; font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 6px; }

    .settings-toggle { position: relative; display: inline-block; width: 42px; height: 24px; flex-shrink: 0; }
    .settings-toggle input { opacity: 0; width: 0; height: 0; }
    .settings-toggle-track {
        position: absolute; inset: 0; background: var(--border); border-radius: 999px;
        cursor: pointer; transition: background .18s ease;
    }
    .settings-toggle-track::before {
        content: ''; position: absolute; width: 18px; height: 18px; left: 3px; top: 3px;
        background: #fff; border-radius: 50%; transition: transform .18s ease; box-shadow: 0 1px 3px rgba(0,0,0,.2);
    }
    .settings-toggle input:checked + .settings-toggle-track { background: var(--primary); }
    .settings-toggle input:checked + .settings-toggle-track::before { transform: translateX(18px); }
    .settings-toggle input:disabled + .settings-toggle-track { opacity: .5; cursor: not-allowed; }

    .settings-modal-backdrop { position: fixed; inset: 0; background: rgba(20,10,4,.45); z-index: 1000; display: flex; align-items: center; justify-content: center; padding: 16px; }
    .settings-modal-card { background: var(--bg-white); border-radius: 20px; width: 100%; max-width: 380px; padding: 26px; box-shadow: 0 24px 70px rgba(45,27,20,.2); }
    .settings-input {
        width: 100%; background: var(--bg-white); border: 1.5px solid var(--border); border-radius: 10px;
        padding: 11px 13px; font-size: 14px; font-family: inherit; color: var(--dark); box-sizing: border-box;
    }
</style>
@endpush

@section('content')
<div class="appearance-page">

    @if (session('success'))
    <div class="alert alert-success mb-16">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
    <div class="alert alert-danger mb-16">{{ $errors->first() }}</div>
    @endif

    {{-- Preferences --}}
    <div class="card settings-card"><div class="card-body">
        <h2>Preferences</h2>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div>
                <label class="settings-field-label">Display currency</label>
                @php
                    $currencies = [
                        'PHP' => ['symbol' => '₱', 'name' => 'Philippine peso'],
                        'USD' => ['symbol' => '$', 'name' => 'US dollar'],
                        'EUR' => ['symbol' => '€', 'name' => 'Euro'],
                        'GBP' => ['symbol' => '£', 'name' => 'British pound'],
                        'JPY' => ['symbol' => '¥', 'name' => 'Japanese yen'],
                        'SGD' => ['symbol' => 'S$', 'name' => 'Singapore dollar'],
                        'AUD' => ['symbol' => 'A$', 'name' => 'Australian dollar'],
                        'KRW' => ['symbol' => '₩', 'name' => 'South Korean won'],
                        'HKD' => ['symbol' => 'HK$', 'name' => 'Hong Kong dollar'],
                        'THB' => ['symbol' => '฿', 'name' => 'Thai baht'],
                        'MYR' => ['symbol' => 'RM', 'name' => 'Malaysian ringgit'],
                        'AED' => ['symbol' => 'د.إ', 'name' => 'UAE dirham'],
                    ];
                @endphp
                <select id="currencySelect" class="settings-select">
                    @foreach ($currencies as $code => $c)
                    <option value="{{ $code }}" data-symbol="{{ $c['symbol'] }}" {{ $user->currency_code === $code ? 'selected' : '' }}>{{ $code }} - {{ $c['name'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="settings-field-label">Default buffer</label>
                <select id="bufferSelect" class="settings-select">
                    @foreach ([0, 5, 10, 15, 20, 25] as $pct)
                    <option value="{{ $pct }}" {{ (int) $user->default_buffer_pct === $pct ? 'selected' : '' }}>{{ $pct }} percent</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div></div>

    {{-- Notifications --}}
    <div class="card settings-card"><div class="card-body">
        <h2>Notifications</h2>
        @foreach ([
            ['field' => 'notify_budget_alerts',       'label' => 'Budget threshold alerts',   'sub' => 'Notify when a trip nears its budget limit'],
            ['field' => 'notify_trip_reminders',      'label' => 'Trip reminders',             'sub' => 'Notify a few days before an upcoming trip'],
            ['field' => 'notify_itinerary_reminders', 'label' => 'Itinerary reminders',        'sub' => 'Notify before a scheduled itinerary stop'],
        ] as $n)
        <div class="settings-row">
            <div>
                <div class="settings-label">{{ $n['label'] }}</div>
                <div class="settings-sub">{{ $n['sub'] }}</div>
            </div>
            <label class="settings-toggle">
                <input type="checkbox" class="settings-notify-toggle" data-field="{{ $n['field'] }}" {{ $user->{$n['field']} ? 'checked' : '' }}>
                <span class="settings-toggle-track"></span>
            </label>
        </div>
        @endforeach
    </div></div>

    {{-- Receipt scanning --}}
    <div class="card settings-card"><div class="card-body">
        <h2>Receipt scanning</h2>
        <div class="settings-row" style="border-top:none;padding-top:0;">
            <div>
                <div class="settings-label">Auto-categorize scanned expenses</div>
                <div class="settings-sub">Let the OCR model assign a category automatically instead of asking you to confirm each time.</div>
            </div>
            <label class="settings-toggle">
                <input type="checkbox" class="settings-notify-toggle" data-field="ocr_auto_categorize" {{ $user->ocr_auto_categorize ? 'checked' : '' }}>
                <span class="settings-toggle-track"></span>
            </label>
        </div>
    </div></div>

    <div class="section-label">Theme</div>
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
</div>

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

(function () {
    var csrf = document.querySelector('meta[name="csrf-token"]').content;

    function patch(url, body) {
        return fetch(url, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify(body),
        });
    }

    // Currency + default buffer
    var currencySelect = document.getElementById('currencySelect');
    var bufferSelect = document.getElementById('bufferSelect');

    function savePreferences() {
        if (!currencySelect || !bufferSelect) return;
        var opt = currencySelect.options[currencySelect.selectedIndex];
        patch('{{ route('settings.preferences') }}', {
            currency_code: currencySelect.value,
            currency_symbol: opt.dataset.symbol,
            default_buffer_pct: parseInt(bufferSelect.value, 10),
        });
    }
    if (currencySelect) currencySelect.addEventListener('change', savePreferences);
    if (bufferSelect) bufferSelect.addEventListener('change', savePreferences);

    // Notification / OCR toggles
    document.querySelectorAll('.settings-notify-toggle').forEach(function (toggle) {
        toggle.addEventListener('change', function () {
            patch('{{ route('settings.notifications') }}', {
                field: toggle.dataset.field,
                value: toggle.checked,
            });
        });
    });
})();
</script>
@endsection
