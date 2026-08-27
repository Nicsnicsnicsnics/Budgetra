@extends('layouts.app')
@section('title', 'Scan Expense')

@push('styles')
<style>
    .dropzone-preview img { display: block; max-width: 100%; }
    .dz-preview-frame { position: relative; border-radius: 10px; overflow: hidden; }
    .dz-preview-frame img { display: block; width: 100%; max-height: 300px; object-fit: contain; background: var(--bg); }
    /* Full dim scrim + spinner over the receipt image WHILE the OCR call is
       in flight — replaces the old below-dropzone alert box, which used the
       global .alert-warning colors (hardcoded cream/brown) and looked badly
       out of place on the dark themes. This overlay is theme-neutral by
       design (a translucent black scrim reads fine on every theme). */
    .dz-scan-overlay {
        position: absolute; inset: 0; display: none; flex-direction: column;
        align-items: center; justify-content: center; gap: 10px;
        background: rgba(10, 12, 20, 0.62); backdrop-filter: blur(2px);
        color: #fff; font-size: 13px; font-weight: 600;
    }
    .dz-scan-overlay.is-active { display: flex; }
    .dz-scan-spinner {
        width: 42px; height: 42px; border-radius: 50%;
        background: rgba(255,255,255,0.14); display: flex; align-items: center;
        justify-content: center; font-size: 18px;
    }
    /* Post-scan result — a small themed pill instead of the full-width
       alert box, so it reads as a quick confirmation rather than a warning
       banner even when it IS reporting a scan failure. */
    .dz-scan-result {
        display: none; align-items: center; gap: 8px; margin-top: 12px;
        padding: 9px 14px; border-radius: var(--radius-sm);
        font-size: 13px; font-weight: 600; text-align: left;
    }
    .dz-scan-result.is-visible { display: flex; }
    .dz-scan-result-success { background: var(--primary-light); color: var(--primary); border: 1px solid var(--primary); }
    .dz-scan-result-warning { background: rgba(217,119,6,0.14); color: var(--warning); border: 1px solid var(--warning); }
    /* Briefly highlights a field OCR just filled in — cleared the moment
       the traveler edits it themselves, so the highlight always means
       "this came from the scan," never a stale leftover. */
    .form-control.ocr-filled { border-color: var(--primary); background: var(--primary-light); }
    .scan-item { transition: background .15s ease; border-radius: var(--radius-sm); }
    .scan-item:hover { background: var(--bg); }
    .scan-select-menu { position: absolute; top: calc(100% + 6px); left: 50%; transform: translateX(-50%); background: var(--bg-white); border: 1px solid var(--border); border-radius: var(--radius-sm); box-shadow: var(--shadow); min-width: 190px; z-index: 50; overflow: hidden; }
    .scan-select-option {
        display: flex; align-items: center; gap: 8px; width: 100%; padding: 11px 14px;
        background: none; border: none; text-align: left; font-size: 13px; font-weight: 600;
        color: var(--text); cursor: pointer; transition: background .15s ease; font-family: inherit;
    }
    .scan-select-option:hover { background: var(--bg); }
    .scan-select-option + .scan-select-option { border-top: 1px solid var(--border-light); }
    .scan-select-option i { color: var(--primary); width: 14px; }
    #dropZone { animation: expenseCardIn .3s ease both; }
    .expense-form-card { animation: expenseCardIn .35s ease both; }
    @keyframes expenseCardIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }

    /* .is-invalid is already used to flag failed server-side validation on
       every form in this app, but was never actually given a style anywhere
       — the class did nothing. This also backs the new inline (client-side,
       as-you-type) validation added on this page. */
    .exp-back {
        display: inline-flex; align-items: center; gap: 6px;
        font-size: 13px; font-weight: 600; color: var(--primary); text-decoration: none;
        background: none; border: none; padding: 0; margin-bottom: 12px;
    }
    .exp-back i { font-size: 11px; }

    /* Empty-on-submit gets the same treatment as the trip planner's Trip
       Details fields: a candy-red ring plus a short shake, so the eye lands
       on the field that needs fixing. Beats :focus so the red survives the
       pointer arriving on the input. */
    .form-control.is-invalid,
    .form-control.is-invalid:focus {
        border-color: #FF3B3B;
        box-shadow: 0 0 0 3px rgba(255,59,59,0.20);
        animation: exp-shake .48s cubic-bezier(.36,.07,.19,.97) both;
    }
    @keyframes exp-shake {
      10%,90%{transform:translateX(-2px);}
      20%,80%{transform:translateX(3px);}
      30%,50%,70%{transform:translateX(-6px);}
      40%,60%{transform:translateX(6px);}
    }
    @media (prefers-reduced-motion:reduce){ .form-control.is-invalid { animation: none; } }

    /* ── Themed select ─────────────────────────────────────────
       A native <select> hands its option list to the browser/OS to
       paint, so the popup ignored every theme token — a blue system
       highlight on a default slab. The real <select> stays in the
       form (it is what the POST, checkValidity() and the OCR autofill
       all talk to) but is drawn over by a trigger + menu built from
       the same tokens as the Expenses filter dropdown. */
    .exp-select { position: relative; }
    .exp-select-native {
        position: absolute; inset: 0; width: 100%; height: 100%;
        margin: 0; opacity: 0; pointer-events: none;
    }
    .exp-select-trigger {
        display: flex; align-items: center; gap: 10px;
        text-align: left; cursor: pointer; font-weight: 700; color: var(--dark);
    }
    .exp-select-value { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .exp-select-value.is-placeholder { color: #9CA3AF; font-weight: 400; }
    .exp-select-caret { font-size: 10px; color: var(--muted); flex-shrink: 0; transition: transform .15s ease; }
    .exp-select.is-open .exp-select-caret { transform: rotate(180deg); }
    .exp-select.is-open .exp-select-trigger { border-color: var(--primary); }
    .exp-select-menu {
        position: absolute; top: calc(100% + 6px); left: 0; right: 0; display: none;
        background: var(--bg-white); border: 1.5px solid var(--border);
        border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,.10);
        max-height: 264px; overflow-y: auto; z-index: 200;
    }
    .exp-select.is-up .exp-select-menu { top: auto; bottom: calc(100% + 6px); }
    .exp-select.is-open .exp-select-menu { display: block; }
    .exp-select-option {
        display: block; width: 100%; text-align: left; padding: 11px 16px;
        border: none; background: none; font-family: inherit; font-size: 13px;
        font-weight: 700; color: var(--dark); cursor: pointer;
    }
    .exp-select-option:hover,
    .exp-select-option:focus-visible { background: var(--bg); outline: none; }
    .exp-select-option[aria-selected="true"] { color: var(--primary); background: var(--primary-light); }
    /* is-invalid / ocr-filled land on the hidden native <select>; the ring
       and the shake belong on the visible trigger beside it. */
    .exp-select-native.is-invalid + .exp-select-trigger {
        border-color: #FF3B3B; box-shadow: 0 0 0 3px rgba(255,59,59,0.20);
        animation: exp-shake .48s cubic-bezier(.36,.07,.19,.97) both;
    }
    @media (prefers-reduced-motion:reduce){ .exp-select-native.is-invalid + .exp-select-trigger { animation: none; } }
    .exp-select-native.ocr-filled + .exp-select-trigger { border-color: var(--primary); background: var(--primary-light); }

    /* Upload prompt — matches the profile photo dropzone. These live in a
       class rather than inline styles because the preview toggle resets
       promptEl.style.display to '', which would wipe an inline display:flex
       and leave the prompt stacked as a plain block when a file is cleared. */
    .dz-prompt {
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        gap: 16px; text-align: center; cursor: pointer;
    }
    .dz-prompt-icon {
        width: 56px; height: 56px; border-radius: 16px; background: var(--primary-light);
        display: flex; align-items: center; justify-content: center;
        color: var(--primary); font-size: 22px; flex-shrink: 0;
    }
    /* .dropzone's own background turns --primary-light on hover/drag/scan —
       the same value as the tile — so the icon square vanished into it. */
    .dropzone:hover .dz-prompt-icon,
    .dropzone.drag-over .dz-prompt-icon,
    .dropzone.ocr-active .dz-prompt-icon { background: var(--bg-white); }
    .dz-prompt-title { font-size: 16px; font-weight: 700; color: var(--dark); }
    .dz-prompt-title span { color: var(--primary); }
    .dz-prompt-hint { font-size: 12px; color: var(--muted); margin-top: 4px; }
    .dz-format-tags { display: flex; gap: 8px; margin-top: 14px; }
    .dz-format-tag {
        font-size: 11px; font-weight: 700; letter-spacing: .02em; color: var(--muted);
        background: var(--bg-white); border: 1px solid var(--border); border-radius: 999px;
        padding: 4px 11px;
    }
    .dropzone:hover .dz-prompt-icon i,
    .dropzone.drag-over .dz-prompt-icon i { transform: translateY(-2px); }
    .dz-prompt-icon i { transition: transform .2s ease; }

    /* Single column: the dropzone sits full-width above the form instead
       of beside it — one thing to look at at a time, top to bottom,
       rather than two panels competing for attention side by side. */
    .scan-layout { display: flex; flex-direction: column; gap: 24px; }
    .scan-layout #dropZone { min-height: 340px; }
</style>
@endpush

@section('content')

@if ($trips->isEmpty())
<div class="empty-state-center" style="min-height:70vh;">
    <div style="width:72px;height:72px;border-radius:20px;background:var(--primary-light);display:flex;align-items:center;justify-content:center;margin-bottom:20px;">
        <i class="fa-solid fa-receipt" style="font-size:32px;color:var(--primary);"></i>
    </div>
    <h2 style="font-weight:700;margin-bottom:8px;">No trips planned yet</h2>
    <p class="text-muted" style="max-width:320px;margin-bottom:24px;">Plan a trip first before logging your expenses.</p>
    <a href="{{ route('trips.plan') }}" class="btn btn-primary btn-lg">
        <i class="fa-solid fa-paper-plane"></i> Plan a Trip
    </a>
</div>
@else

{{-- margin:auto, not 0 auto — .dash-content is a flex column, so auto block
     margins split its leftover height evenly above and below instead of
     letting all the slack pile up underneath the cards. --}}
<div style="max-width:1180px;width:100%;margin:auto;">
    <a href="{{ route('expenses.index') }}{{ request('trip_id') ? '?trip_id='.request('trip_id') : '' }}" class="exp-back">
        <i class="fa-solid fa-arrow-left"></i> Back to Expenses
    </a>
    {{-- One unified card instead of two separate boxes sitting side by
         side — a single header/intro makes this read as one "add an
         expense" step, with the receipt scan just being how you can
         optionally fill it in, not a disconnected second panel. --}}
    <div class="card expense-form-card"><div class="card-body" style="padding:32px;">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:4px;">
            <div style="width:38px;height:38px;border-radius:10px;background:var(--primary-light);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="fa-solid fa-receipt" style="color:var(--primary);font-size:15px;"></i>
            </div>
            <h3 style="font-size:20px;margin:0;">Add Expense</h3>
        </div>
        <p class="text-muted mb-16" style="font-size:14px;">Upload a receipt to auto-fill the details below, or enter them yourself.</p>

        @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <div class="scan-layout">
            {{-- Drop zone --}}
            <div>
                <div class="dropzone" id="dropZone">
                    {{-- A <label for> opens the picker natively, so the whole
                         prompt is clickable without the extra Select File button. --}}
                    <label for="receiptFile" id="dropzonePrompt" class="dz-prompt">
                        <div class="dz-prompt-icon"><i class="fa-solid fa-cloud-arrow-up"></i></div>
                        <div>
                            <div class="dz-prompt-title"><span>Click to upload</span> a receipt</div>
                            <div class="dz-prompt-hint">or drag and drop it in here</div>
                            <div class="dz-format-tags">
                                <span class="dz-format-tag">PNG</span>
                                <span class="dz-format-tag">JPG</span>
                                <span class="dz-format-tag">Up to 10MB</span>
                            </div>
                        </div>
                    </label>
                    <input type="file" id="receiptFile" accept="image/*,application/pdf" style="display:none;">

                    <div class="dropzone-preview" id="dropzonePreview">
                        <div class="dz-preview-frame">
                            <img id="dropzonePreviewImg" alt="Receipt preview">
                            <div class="dz-scan-overlay" id="ocrScanOverlay">
                                <div class="dz-scan-spinner"><i class="fa-solid fa-spinner fa-spin"></i></div>
                                <div>Scanning receipt...</div>
                            </div>
                        </div>
                        <div id="ocrStatus" class="dz-scan-result"></div>
                        <button type="button" id="dropzoneClear" class="btn btn-outline btn-sm" style="margin-top:14px;">
                            <i class="fa-solid fa-arrow-rotate-left"></i> Choose a different file
                        </button>
                    </div>
                </div>
            </div>

            {{-- Expense Details form --}}
            {{-- novalidate: the fields keep their `required` attributes (checkValidity()
     still reads them, and the server validates regardless), but the browser's
     own bubble is suppressed. Without this the native UI blocked submission
     outright and the submit event never fired, so the shake never ran. --}}
<form method="POST" action="{{ route('expenses.store') }}" enctype="multipart/form-data" id="expenseForm" novalidate>
                @csrf

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                    @php
                        $tripSel   = old('trip_id', request('trip_id'));
                        $tripLabel = fn ($t) => $t->destination.' ('.$t->start_date->format('M Y').')';
                        $tripPick  = $trips->first(fn ($t) => (string) $t->id === (string) $tripSel);
                    @endphp
                    <div class="form-group">
                        <label class="form-label" for="trip_id" id="trip_id-label">Trip</label>
                        <div class="input-wrapper">
                            <span class="input-icon"><i class="fa-solid fa-route"></i></span>
                            {{-- The <select> is the real control — kept for the POST,
                                 checkValidity() and the OCR autofill — and hidden behind
                                 the themed trigger + menu below it. --}}
                            <div class="exp-select" data-exp-select>
                                <select name="trip_id" id="trip_id" tabindex="-1" aria-hidden="true"
                                        class="form-control exp-select-native {{ $errors->has('trip_id') ? 'is-invalid' : '' }}" required>
                                    <option value="">Select trip</option>
                                    @foreach ($trips as $trip)
                                    <option value="{{ $trip->id }}"
                                        {{ (string) $tripSel === (string) $trip->id ? 'selected' : '' }}>
                                        {{ $tripLabel($trip) }}
                                    </option>
                                    @endforeach
                                </select>
                                <button type="button" class="form-control exp-select-trigger"
                                        aria-haspopup="listbox" aria-expanded="false"
                                        aria-labelledby="trip_id-label trip_id-value">
                                    <span class="exp-select-value {{ $tripPick ? '' : 'is-placeholder' }}"
                                          id="trip_id-value" data-placeholder="Select trip">{{ $tripPick ? $tripLabel($tripPick) : 'Select trip' }}</span>
                                    <i class="fa-solid fa-chevron-down exp-select-caret"></i>
                                </button>
                                <div class="exp-select-menu" role="listbox" aria-labelledby="trip_id-label">
                                    <button type="button" class="exp-select-option" role="option" data-value=""
                                            aria-selected="{{ $tripPick ? 'false' : 'true' }}">Select trip</button>
                                    @foreach ($trips as $trip)
                                    <button type="button" class="exp-select-option" role="option" data-value="{{ $trip->id }}"
                                            aria-selected="{{ $tripPick && $tripPick->id === $trip->id ? 'true' : 'false' }}">{{ $tripLabel($trip) }}</button>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        @error('trip_id')<div class="form-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="amount">Amount ({{ currency_symbol() }})</label>
                        <div class="input-wrapper">
                            <span class="input-icon"><i class="fa-solid fa-coins"></i></span>
                            <input type="number" id="amount" name="amount" step="0.01" min="0.01"
                                   value="{{ old('amount') }}"
                                   class="form-control {{ $errors->has('amount') ? 'is-invalid' : '' }}"
                                   placeholder="0.00" required>
                        </div>
                        @error('amount')<div class="form-error">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                    @php $catSel = old('category'); @endphp
                    <div class="form-group">
                        <label class="form-label" for="category" id="category-label">Category</label>
                        <div class="input-wrapper">
                            <span class="input-icon"><i class="fa-solid fa-tag"></i></span>
                            <div class="exp-select" data-exp-select>
                                <select name="category" id="category" tabindex="-1" aria-hidden="true"
                                        class="form-control exp-select-native {{ $errors->has('category') ? 'is-invalid' : '' }}" required>
                                    <option value="">Select category</option>
                                    @foreach ($categories as $cat)
                                    <option value="{{ $cat }}" {{ $catSel === $cat ? 'selected' : '' }}>{{ $cat }}</option>
                                    @endforeach
                                </select>
                                <button type="button" class="form-control exp-select-trigger"
                                        aria-haspopup="listbox" aria-expanded="false"
                                        aria-labelledby="category-label category-value">
                                    <span class="exp-select-value {{ in_array($catSel, $categories, true) ? '' : 'is-placeholder' }}"
                                          id="category-value" data-placeholder="Select category">{{ in_array($catSel, $categories, true) ? $catSel : 'Select category' }}</span>
                                    <i class="fa-solid fa-chevron-down exp-select-caret"></i>
                                </button>
                                <div class="exp-select-menu" role="listbox" aria-labelledby="category-label">
                                    <button type="button" class="exp-select-option" role="option" data-value=""
                                            aria-selected="{{ in_array($catSel, $categories, true) ? 'false' : 'true' }}">Select category</button>
                                    @foreach ($categories as $cat)
                                    <button type="button" class="exp-select-option" role="option" data-value="{{ $cat }}"
                                            aria-selected="{{ $catSel === $cat ? 'true' : 'false' }}">{{ $cat }}</button>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        @error('category')<div class="form-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="expense_date">Date</label>
                        <div class="input-wrapper">
                            <span class="input-icon"><i class="fa-solid fa-calendar-days"></i></span>
                            <input type="date" id="expense_date" name="expense_date"
                                   value="{{ old('expense_date', date('Y-m-d')) }}" min="2000-01-01" max="2099-12-31"
                                   class="form-control {{ $errors->has('expense_date') ? 'is-invalid' : '' }}" required>
                        </div>
                        @error('expense_date')<div class="form-error">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="description">Description / Merchant</label>
                    <div class="input-wrapper">
                        <span class="input-icon"><i class="fa-solid fa-store"></i></span>
                        <input type="text" id="description" name="description" required
                               value="{{ old('description') }}"
                               class="form-control" placeholder="e.g. L'Osteria Pizza">
                    </div>
                </div>

                {{-- Hidden real file input for form submission --}}
                <input type="file" name="receipt" id="receiptSubmit" style="display:none;" accept="image/*,application/pdf">

                <button type="submit" class="btn btn-primary btn-lg btn-block mt-8">
                    <i class="fa-solid fa-floppy-disk"></i> Save Expense
                </button>
            </form>
        </div></div>
    </div>
</div>

@endif
@endsection

@push('scripts')
<script>
// Themed <select>. The native control stays the source of truth — it holds the
// value, carries `required` for checkValidity(), and is what the OCR autofill
// writes to — while the trigger + menu rendered beside it provide the visible
// UI, because a native option list is painted by the browser and can't be
// themed. State classes (is-invalid, ocr-filled) still land on the select and
// are reflected onto the trigger by the adjacent-sibling CSS rules.
(function () {
    var wraps = document.querySelectorAll('[data-exp-select]');
    if (!wraps.length) return;

    var openWrap = null;

    function closeOpen(refocus) {
        if (!openWrap) return;
        var w = openWrap;
        openWrap = null;
        w.classList.remove('is-open', 'is-up');
        w.querySelector('.exp-select-trigger').setAttribute('aria-expanded', 'false');
        if (refocus) w.querySelector('.exp-select-trigger').focus();
    }

    wraps.forEach(function (wrap) {
        var sel     = wrap.querySelector('.exp-select-native');
        var trigger = wrap.querySelector('.exp-select-trigger');
        var value   = wrap.querySelector('.exp-select-value');
        var menu    = wrap.querySelector('.exp-select-menu');
        var options = Array.prototype.slice.call(menu.querySelectorAll('.exp-select-option'));
        var placeholder = value.getAttribute('data-placeholder') || '';

        function sync() {
            var match = null;
            options.forEach(function (o) {
                var hit = o.getAttribute('data-value') === sel.value;
                if (hit) match = o;
                o.setAttribute('aria-selected', hit ? 'true' : 'false');
            });
            var chosen = sel.value !== '' && match;
            value.textContent = chosen ? match.textContent.trim() : placeholder;
            value.classList.toggle('is-placeholder', !chosen);
        }

        function open() {
            if (openWrap === wrap) { closeOpen(false); return; }
            closeOpen(false);
            // Flip the menu above the field when there isn't room below it,
            // so the last options aren't stranded off the bottom of the page.
            var below = window.innerHeight - trigger.getBoundingClientRect().bottom;
            wrap.classList.toggle('is-up', below < 200 && trigger.getBoundingClientRect().top > below);
            wrap.classList.add('is-open');
            trigger.setAttribute('aria-expanded', 'true');
            openWrap = wrap;
            var cur = menu.querySelector('[aria-selected="true"]') || options[0];
            if (cur) cur.focus();
        }

        trigger.addEventListener('click', open);
        trigger.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') { e.preventDefault(); open(); }
        });

        options.forEach(function (o, i) {
            o.addEventListener('click', function () {
                sel.value = o.getAttribute('data-value');
                // Drives sync() below, and the form's own validation + OCR
                // listeners, exactly as picking from a native list would.
                sel.dispatchEvent(new Event('change', { bubbles: true }));
                closeOpen(true);
            });
            o.addEventListener('keydown', function (e) {
                if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                    e.preventDefault();
                    var next = options[(i + (e.key === 'ArrowDown' ? 1 : options.length - 1)) % options.length];
                    if (next) next.focus();
                } else if (e.key === 'Home' || e.key === 'End') {
                    e.preventDefault();
                    options[e.key === 'Home' ? 0 : options.length - 1].focus();
                }
            });
        });

        menu.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { e.stopPropagation(); closeOpen(true); }
        });

        // The select is invisible, so a programmatic focus() on it — which is
        // what the empty-field handler below does to the first invalid control
        // — has to land on the trigger instead.
        sel.addEventListener('focus', function () { trigger.focus(); });
        sel.addEventListener('change', sync);
        sync();
    });

    document.addEventListener('mousedown', function (e) {
        if (openWrap && !openWrap.contains(e.target)) closeOpen(false);
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeOpen(true);
    });
})();
</script>
<script>
(function () {
    var dropZone       = document.getElementById('dropZone');
    var fileInput      = document.getElementById('receiptFile');
    var submitInput    = document.getElementById('receiptSubmit');
    var scanOverlay    = document.getElementById('ocrScanOverlay');
    var statusDiv      = document.getElementById('ocrStatus');
    var amountInput    = document.getElementById('amount');
    var dateInput      = document.getElementById('expense_date');
    var descInput      = document.getElementById('description');
    var categorySelect = document.getElementById('category');
    var promptEl       = document.getElementById('dropzonePrompt');
    var previewWrap    = document.getElementById('dropzonePreview');
    var previewImg     = document.getElementById('dropzonePreviewImg');
    var clearBtn       = document.getElementById('dropzoneClear');

    if (!dropZone) return; // no trips: the form isn't on the page at all

    function showPreview(file) {
        var reader = new FileReader();
        reader.onload = function (e) {
            previewImg.src = e.target.result;
            promptEl.style.display = 'none';
            previewWrap.style.display = 'block';
        };
        reader.readAsDataURL(file);
    }

    function clearPreview() {
        previewImg.src = '';
        previewWrap.style.display = 'none';
        promptEl.style.display = '';
        fileInput.value = '';
        submitInput.value = '';
        scanOverlay.classList.remove('is-active');
        statusDiv.classList.remove('is-visible');
        [amountInput, dateInput, descInput, categorySelect].forEach(function (field) {
            if (field) field.classList.remove('ocr-filled');
        });
    }

    clearBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        clearPreview();
    });

    function setStatus(message, isWarning) {
        statusDiv.innerHTML = '<i class="fa-solid ' + (isWarning ? 'fa-triangle-exclamation' : 'fa-circle-check') + '"></i> ' + message;
        statusDiv.classList.toggle('dz-scan-result-warning', isWarning);
        statusDiv.classList.toggle('dz-scan-result-success', !isWarning);
        statusDiv.classList.add('is-visible');
    }

    // Briefly highlights a field OCR just filled, clearing the highlight
    // the moment the traveler touches that field themselves so it never
    // looks like a stale leftover from an earlier scan.
    function markOcrFilled(field) {
        if (!field) return;
        field.classList.add('ocr-filled');
        var clear = function () { field.classList.remove('ocr-filled'); };
        field.addEventListener('input', clear, { once: true });
        field.addEventListener('change', clear, { once: true });
    }

    function scanReceipt(file) {
        scanOverlay.classList.add('is-active');
        statusDiv.classList.remove('is-visible');
        dropZone.classList.add('ocr-active');

        var formData = new FormData();
        formData.append('receipt', file);
        formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);

        fetch('/expenses/ocr', {
            method: 'POST',
            body: formData,
            // Without this, Laravel can't tell this is an AJAX request and
            // responds to a validation failure with an HTML redirect instead
            // of JSON — which would fail r.json() below with a parse error
            // rather than a real message.
            headers: { 'Accept': 'application/json' }
        })
            .then(function (r) {
                if (!r.ok) {
                    // Validation error (e.g. file too large/unsupported format) or
                    // server error — still valid JSON, just not OCR fields, so it
                    // has to be handled here rather than falling through silently.
                    return r.json().then(function (body) {
                        var msg = (body && body.errors && body.errors.receipt)
                            ? body.errors.receipt[0]
                            : "Couldn't scan this receipt.";
                        throw new Error(msg);
                    });
                }
                return r.json();
            })
            .then(function (data) {
                scanOverlay.classList.remove('is-active');
                dropZone.classList.remove('ocr-active');
                if (data.amount)      { amountInput.value = data.amount; markOcrFilled(amountInput); }
                if (data.date)        { dateInput.value   = data.date;   markOcrFilled(dateInput); }
                if (data.description) { descInput.value   = data.description; markOcrFilled(descInput); }
                if (data.category && categorySelect) {
                    categorySelect.value = data.category;
                    // The themed trigger mirrors the select off `change`, which a
                    // scripted .value assignment does not fire on its own. Dispatch
                    // before markOcrFilled, or its once-per-change listener would
                    // eat this event and clear the highlight immediately.
                    categorySelect.dispatchEvent(new Event('change', { bubbles: true }));
                    markOcrFilled(categorySelect);
                }

                if (data.amount) {
                    setStatus('Receipt scanned — details filled in below.', false);
                } else {
                    setStatus("Couldn't auto-read this receipt. Please fill in the details manually.", true);
                }
                setTimeout(function () { statusDiv.classList.remove('is-visible'); }, 4000);
            })
            .catch(function (err) {
                scanOverlay.classList.remove('is-active');
                dropZone.classList.remove('ocr-active');
                setStatus((err && err.message) || 'Scan failed. Please fill in the details manually.', true);
                setTimeout(function () { statusDiv.classList.remove('is-visible'); }, 5000);
            });
    }

    function handleFile(file) {
        if (!file) return;

        // Copy the file to the real submit input so the form POST includes it.
        var dt = new DataTransfer();
        dt.items.add(file);
        submitInput.files = dt.files;

        showPreview(file);
        scanReceipt(file);
    }

    // Click to browse
    fileInput.addEventListener('change', function () { handleFile(this.files[0]); });

    // Drag and drop
    dropZone.addEventListener('dragover', function (e) {
        e.preventDefault(); this.classList.add('drag-over');
    });
    dropZone.addEventListener('dragleave', function () { this.classList.remove('drag-over'); });
    dropZone.addEventListener('drop', function (e) {
        e.preventDefault(); this.classList.remove('drag-over');
        handleFile(e.dataTransfer.files[0]);
    });
})();
</script>
<script>
(function () {
    var form = document.getElementById('expenseForm');
    if (!form) return;

    // Several fields are now wrapped in .input-wrapper (icon + control), so
    // the error message's insertion point can't just be "next to the field"
    // anymore — it has to sit after the WRAPPER, matching where the
    // server-rendered @@error() block already renders (a sibling of
    // .input-wrapper, not of the input/select itself), or the live and
    // server-side errors would end up in different places.
    function errorAnchor(field) {
        return field.closest('.input-wrapper') || field;
    }

    // The red ring + shake carry the message on their own; the extra
    // "This field is required." line under every field was noise. Any
    // previously-inserted line is still cleaned up below.
    function showFieldError(field) {
        field.classList.add('is-invalid');
    }

    function clearFieldError(field) {
        field.classList.remove('is-invalid');
        var anchor = errorAnchor(field);
        var msg = anchor.nextElementSibling;
        if (msg && msg.classList.contains('form-error-live')) msg.remove();
    }


    function validateField(field) {
        if (field.checkValidity()) { clearFieldError(field); return true; }
        showFieldError(field);
        return false;
    }

    var fields = form.querySelectorAll('[required]');
    fields.forEach(function (field) {
        field.addEventListener('blur', function () { validateField(field); });
        field.addEventListener('input', function () {
            if (field.classList.contains('is-invalid')) validateField(field);
        });
        field.addEventListener('change', function () {
            if (field.classList.contains('is-invalid')) validateField(field);
        });
    });

    form.addEventListener('submit', function (e) {
        var allValid = true;
        var invalid  = [];
        fields.forEach(function (field) {
            if (!validateField(field)) { allValid = false; invalid.push(field); }
        });
        if (!allValid) {
            e.preventDefault();
            // A CSS animation only plays when the class lands, so pressing Save
            // again on a still-empty field would sit there doing nothing. Drop
            // the class and re-add it next frame to replay the shake.
            invalid.forEach(function (f) { f.classList.remove('is-invalid'); });
            requestAnimationFrame(function () {
                invalid.forEach(function (f) { f.classList.add('is-invalid'); });
            });
            if (invalid[0] && invalid[0].focus) invalid[0].focus({ preventScroll: false });
            return;
        }

        var btn = form.querySelector('button[type="submit"]');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
        }
    });
})();
</script>
@endpush
