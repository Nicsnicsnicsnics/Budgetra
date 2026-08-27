@extends('layouts.app')
@section('title', 'Edit Expense')

@push('styles')
<style>
    .edit-expense-card { max-width: 560px; margin: 0 auto; animation: expenseEditIn .3s ease both; }
    @keyframes expenseEditIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }

    .edit-receipt-preview {
        display: flex; align-items: center; gap: 12px; background: var(--bg); border: 1px solid var(--border);
        border-radius: var(--radius-sm); padding: 10px 14px; text-decoration: none; transition: border-color .15s ease;
    }
    .edit-receipt-preview:hover { border-color: var(--primary); }
    .edit-receipt-preview img { width: 44px; height: 44px; border-radius: 8px; object-fit: cover; flex-shrink: 0; }
    .edit-receipt-preview span { font-size: 13px; font-weight: 600; color: var(--text); }
    .edit-receipt-preview i { color: var(--primary); margin-right: 4px; }

    /* .is-invalid is already used to flag failed server-side validation on
       every form in this app, but was never actually given a style anywhere
       — the class did nothing. This also backs the new inline (client-side,
       as-you-type) validation added on this page. */
    .form-control.is-invalid { border-color: var(--danger); }
    .form-control.is-invalid:focus { border-color: var(--danger); box-shadow: 0 0 0 3px rgba(220,38,38,0.12); }
</style>
@endpush

@section('content')

<div class="edit-expense-card">
    <div style="display:flex;align-items:center;justify-content:space-between;" class="mb-24">
        <div>
            <h1 style="font-size:22px;">Edit Expense</h1>
            <p class="text-muted" style="font-size:13px;">Update the details for this expense.</p>
        </div>
        <a href="{{ route('expenses.index') }}" class="btn btn-outline btn-sm">← Back</a>
    </div>

    @if ($errors->any())
    <div class="alert alert-danger">
        <ul style="margin:0;padding-left:1.2em;">
            @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
    </div>
    @endif

    <div class="card"><div class="card-body">
        <form method="POST" action="{{ route('expenses.update', $expense) }}" id="expenseEditForm" enctype="multipart/form-data">
            @csrf @method('PUT')

            <div class="form-group">
                <label class="form-label" for="trip_id">Trip</label>
                <select name="trip_id" id="trip_id" class="form-control {{ $errors->has('trip_id') ? 'is-invalid' : '' }}" required>
                    @foreach ($trips as $trip)
                    <option value="{{ $trip->id }}" {{ old('trip_id', $expense->trip_id) == $trip->id ? 'selected' : '' }}>
                        {{ $trip->destination }} ({{ $trip->start_date->format('M Y') }})
                    </option>
                    @endforeach
                </select>
                @error('trip_id')<div class="form-error">{{ $message }}</div>@enderror
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                <div class="form-group">
                    <label class="form-label" for="amount">Amount ({{ currency_symbol() }})</label>
                    <input type="number" step="0.01" min="0.01" id="amount" name="amount"
                           class="form-control {{ $errors->has('amount') ? 'is-invalid' : '' }}"
                           value="{{ old('amount', $expense->amount) }}" required>
                    @error('amount')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="category">Category</label>
                    <select name="category" id="category" class="form-control {{ $errors->has('category') ? 'is-invalid' : '' }}" required>
                        @foreach ($categories as $cat)
                        <option value="{{ $cat }}" {{ old('category', $expense->category) === $cat ? 'selected' : '' }}>{{ $cat }}</option>
                        @endforeach
                    </select>
                    @error('category')<div class="form-error">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="description">Description</label>
                <input type="text" id="description" name="description" class="form-control"
                       value="{{ old('description', $expense->description) }}" placeholder="e.g. L'Osteria Pizza">
            </div>

            <div class="form-group">
                <label class="form-label" for="expense_date">Date</label>
                <input type="date" id="expense_date" name="expense_date" min="2000-01-01" max="2099-12-31"
                       class="form-control {{ $errors->has('expense_date') ? 'is-invalid' : '' }}"
                       value="{{ old('expense_date', $expense->expense_date->format('Y-m-d')) }}" required>
                @error('expense_date')<div class="form-error">{{ $message }}</div>@enderror
            </div>

            <div class="form-group">
                <label class="form-label">Receipt</label>
                @if ($expense->receipt_path)
                <a href="{{ Storage::url($expense->receipt_path) }}" target="_blank" rel="noopener" class="edit-receipt-preview mb-8" style="display:flex;">
                    <img src="{{ Storage::url($expense->receipt_path) }}" alt="Receipt preview" loading="lazy">
                    <span><i class="fa-solid fa-paperclip"></i> View attached receipt</span>
                </a>
                <input type="file" name="receipt" id="receipt" accept="image/jpeg,image/png,image/webp"
                       class="form-control {{ $errors->has('receipt') ? 'is-invalid' : '' }}">
                <small class="text-muted" style="font-size:12px;">Choose a file to replace the receipt above.</small>
                @else
                <input type="file" name="receipt" id="receipt" accept="image/jpeg,image/png,image/webp"
                       class="form-control {{ $errors->has('receipt') ? 'is-invalid' : '' }}">
                <small class="text-muted" style="font-size:12px;">No receipt attached yet — optional.</small>
                @endif
                @error('receipt')<div class="form-error">{{ $message }}</div>@enderror
            </div>

            <div style="display:flex;gap:10px;" class="mt-8">
                <button type="submit" class="btn btn-primary" style="flex:1;">
                    <i class="fa-solid fa-floppy-disk"></i> Update Expense
                </button>
                <a href="{{ route('expenses.index') }}" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div></div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var form = document.getElementById('expenseEditForm');
    if (!form) return;

    function showFieldError(field, message) {
        field.classList.add('is-invalid');
        var msg = field.nextElementSibling;
        if (!msg || !msg.classList.contains('form-error-live')) {
            msg = document.createElement('div');
            msg.className = 'form-error form-error-live';
            field.insertAdjacentElement('afterend', msg);
        }
        msg.textContent = message;
    }

    function clearFieldError(field) {
        field.classList.remove('is-invalid');
        var msg = field.nextElementSibling;
        if (msg && msg.classList.contains('form-error-live')) msg.remove();
    }

    function friendlyMessage(field) {
        if (field.validity.valueMissing) return 'This field is required.';
        if (field.name === 'amount' && field.validity.rangeUnderflow) return 'Amount must be greater than 0.';
        if (field.validity.badInput || field.validity.typeMismatch) return 'Please enter a valid value.';
        return field.validationMessage || 'Please check this field.';
    }

    function validateField(field) {
        if (field.checkValidity()) { clearFieldError(field); return true; }
        showFieldError(field, friendlyMessage(field));
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
        fields.forEach(function (field) { if (!validateField(field)) allValid = false; });
        if (!allValid) { e.preventDefault(); return; }

        var btn = form.querySelector('button[type="submit"]');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Updating...';
        }
    });
})();
</script>
@endpush
