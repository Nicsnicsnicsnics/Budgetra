<?php
namespace App\Http\Controllers\Traveler;

use App\Exceptions\CurrencyUnavailable;
use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Trip;
use App\Services\CurrencyConverterService;
use App\Support\PlaceCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ExpenseController extends Controller
{
    private const CATEGORIES = ['Transportation','Accommodation','Food','Activities','Shopping','Emergency Expenses'];

    /** Currencies an expense may be recorded in — anything we can render. */
    private static function currencyCodes(): array
    {
        return array_keys(PlaceCatalog::CURRENCY_SYMBOLS);
    }

    /**
     * Turns whatever the traveller typed into the peso figure the rest of the app
     * measures against, keeping the original alongside it.
     *
     * `amount` on the way in is what they typed, in `amount_currency`. On the way
     * out `amount` is pesos, and `amount_original`/`amount_currency` record what
     * was actually spent — so an edit re-converts from the original rather than
     * converting an already-converted number a second time.
     */
    private function resolveAmountInPesos(array $validated): array
    {
        $code  = strtoupper((string) ($validated['amount_currency'] ?? 'PHP')) ?: 'PHP';
        $typed = (float) $validated['amount'];

        if ($code === 'PHP') {
            $validated['amount_currency'] = 'PHP';
            $validated['amount_original'] = null;   // nothing was converted
            return $validated;
        }

        $rate = (new CurrencyConverterService())->rateToPhp($code);
        if ($rate === null) {
            throw CurrencyUnavailable::for($code);
        }

        $validated['amount']          = round($typed * $rate, 2);
        $validated['amount_original'] = $typed;
        $validated['amount_currency'] = $code;

        return $validated;
    }

    /**
     * What a trip's expenses should default to being typed in: the destination's
     * own currency, because that's what the traveller is handing over at the till.
     */
    public static function defaultCurrencyForTrip(?Trip $trip): string
    {
        return $trip?->destination_currency ?: 'PHP';
    }

    public function index(Request $request)
    {
        $user  = auth()->user();
        // accessibleTrips(): a group member logs their own spending against
        // the shared trip, so it has to appear in this selector.
        $trips = $user->accessibleTrips()->latest()->get()
            ->filter(fn ($t) => in_array($t->resolved_status, ['active', 'upcoming', 'past'], true))
            ->values();

        // Scoped by trip, not by who logged it. On a group trip everyone needs
        // to see the whole group's spending — the trip's own totals (and the
        // per-person split) already count every member's expenses, so listing
        // only your own here contradicted the numbers shown beside it.
        // Restricted to trips the traveller may open, so a stray ?trip_id
        // can't expose someone else's expenses.
        $accessibleIds = $user->accessibleTrips()->pluck('id');
        $query = Expense::with(['trip', 'user:id,full_name'])
            ->whereIn('trip_id', $accessibleIds)
            ->latest('expense_date');

        // The page is built around viewing one trip's expenses at a time
        // (destination selector, single-trip "Add Expense" link) — default
        // to the first trip whenever none is specified, not just when
        // there's exactly one. Matches the same default the view already
        // assumes for which destination looks "selected".
        $tripId = $request->filled('trip_id') ? $request->trip_id : $trips->first()?->id;

        if ($tripId)                       $query->where('trip_id', $tripId);
        if ($request->filled('category'))  $query->where('category', $request->category);
        // strtotime() guards against a malformed date reaching the query —
        // on Postgres (the real database), comparing a date column against
        // a string that isn't a valid date throws a QueryException instead
        // of just matching nothing, crashing the whole page over what
        // should just be an ignorable bad filter value.
        if ($request->filled('date_from') && strtotime($request->date_from) !== false) {
            $query->where('expense_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to') && strtotime($request->date_to) !== false) {
            $query->where('expense_date', '<=', $request->date_to);
        }

        $expenses   = $query->paginate(20)->withQueryString();
        $categories = self::CATEGORIES;

        return view('traveler.expenses.index', compact('expenses', 'trips', 'categories'));
    }

    public function create(Request $request)
    {
        $trips      = auth()->user()->accessibleTrips()->latest()->get()
            ->filter(fn ($t) => in_array($t->resolved_status, ['active', 'upcoming', 'past'], true))
            ->values();
        $categories = self::CATEGORIES;

        // Default to the currency of whichever trip is preselected — on a Japan
        // trip the traveller is handing over yen, so that's what the form should
        // be ready to accept.
        $preselected = $request->filled('trip_id')
            ? $trips->firstWhere('id', (int) $request->input('trip_id'))
            : $trips->first();

        $defaultCurrency = self::defaultCurrencyForTrip($preselected);

        return view('traveler.expenses.create', compact('trips', 'categories', 'defaultCurrency'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'trip_id'         => 'required|exists:trips,id',
            'amount'          => 'required|numeric|min:0.01',
            'amount_currency' => ['nullable', 'string', 'size:3', Rule::in(self::currencyCodes())],
            'category'        => 'required|in:' . implode(',', self::CATEGORIES),
            'description'     => 'nullable|string|max:500',
            'expense_date'    => 'required|date',
            'receipt'         => 'nullable|image|mimes:jpeg,png,jpg,webp|max:10240',
        ]);

        abort_if(
            !auth()->user()->canAccessTrip((int) $validated['trip_id']),
            403
        );

        // A traveller in Japan types what the receipt says — ¥3,500 — and this
        // turns it into the peso figure budgets are measured against, keeping
        // the yen original alongside. Refuses rather than guessing a rate.
        try {
            $validated = $this->resolveAmountInPesos($validated);
        } catch (CurrencyUnavailable $e) {
            return back()->withInput()->withErrors(['amount' => $e->getMessage()]);
        }

        if ($request->hasFile('receipt')) {
            $validated['receipt_path'] = $request->file('receipt')->store('receipts', 'public');
        }
        unset($validated['receipt']);

        $validated['user_id'] = auth()->id();

        // The file above is already on disk by this point — if creating the
        // actual expense record fails for any reason, that file would
        // otherwise orphan with nothing left to ever reference or clean it
        // up, the same leak just fixed in the OCR scan step. Clean it up
        // before letting the failure propagate normally.
        try {
            $expense = Expense::create($validated);
        } catch (\Throwable $e) {
            if (!empty($validated['receipt_path'])) {
                Storage::disk('public')->delete($validated['receipt_path']);
            }
            throw $e;
        }

        \App\Observers\ExpenseObserver::syncBudgetForExpense($expense);

        return redirect()->route('expenses.index')->with('success', 'Expense recorded.');
    }

    public function edit(Expense $expense)
    {
        // Anyone on the trip may edit its expenses, not just whoever logged
        // them — a shared trip's ledger is shared. The row still shows who
        // recorded it, so attribution isn't lost.
        abort_if(!auth()->user()->canAccessTrip((int) $expense->trip_id), 403);
        $trips      = auth()->user()->accessibleTrips()->latest()->get();
        $categories = self::CATEGORIES;
        return view('traveler.expenses.edit', compact('expense', 'trips', 'categories'));
    }

    public function update(Request $request, Expense $expense)
    {
        // Trip membership, not authorship — see edit().
        abort_if(!auth()->user()->canAccessTrip((int) $expense->trip_id), 403);

        $validated = $request->validate([
            'trip_id'         => 'required|exists:trips,id',
            'amount'          => 'required|numeric|min:0.01',
            'amount_currency' => ['nullable', 'string', 'size:3', Rule::in(self::currencyCodes())],
            'category'        => 'required|in:' . implode(',', self::CATEGORIES),
            'description'     => 'nullable|string|max:500',
            'expense_date'    => 'required|date',
            'receipt'         => 'nullable|image|mimes:jpeg,png,jpg,webp|max:10240',
        ]);

        // exists:trips,id above only checks the trip is real, not that it's
        // this traveler's — same check store() already applies, needed here
        // too since trip_id can be changed on edit, not just set once.
        abort_if(
            !auth()->user()->canAccessTrip((int) $validated['trip_id']),
            403
        );

        // The edit form shows the ORIGINAL amount (¥3,500), not the peso figure,
        // so what comes back is re-converted from scratch. Converting the peso
        // value again is the double-conversion bug this shape exists to prevent.
        try {
            $validated = $this->resolveAmountInPesos($validated);
        } catch (CurrencyUnavailable $e) {
            return back()->withInput()->withErrors(['amount' => $e->getMessage()]);
        }

        $oldReceiptPath = $expense->receipt_path;
        $replacingReceipt = $request->hasFile('receipt');

        if ($replacingReceipt) {
            // Store the new file first, but don't delete the old one yet —
            // if update() below fails, the old file needs to stay intact
            // (nothing changed), and only the just-stored NEW file should
            // be cleaned up, not both.
            $validated['receipt_path'] = $request->file('receipt')->store('receipts', 'public');
        }
        unset($validated['receipt']);

        try {
            $expense->update($validated);
        } catch (\Throwable $e) {
            if ($replacingReceipt) {
                Storage::disk('public')->delete($validated['receipt_path']);
            }
            throw $e;
        }

        // Only remove the old receipt once the swap has actually succeeded.
        if ($replacingReceipt && $oldReceiptPath) {
            Storage::disk('public')->delete($oldReceiptPath);
        }

        return redirect()->route('expenses.index')->with('success', 'Expense updated.');
    }

    public function destroy(Expense $expense)
    {
        // Trip membership, not authorship — see edit().
        abort_if(!auth()->user()->canAccessTrip((int) $expense->trip_id), 403);

        if ($expense->receipt_path) {
            Storage::disk('public')->delete($expense->receipt_path);
        }

        $expense->delete();
        return redirect()->route('expenses.index')->with('success', 'Expense deleted.');
    }

    public function ocr(Request $request, \App\Services\OcrService $ocrService)
    {
        $request->validate([
            'receipt' => 'required|file|mimes:jpeg,png,jpg,webp,pdf|max:10240',
            'trip_id' => 'nullable|exists:trips,id',
        ]);

        // Which trip the receipt belongs to decides what an ambiguous symbol
        // means — '¥' is yen on a Japan trip and yuan on a China one — and what
        // the amount should default to being read as.
        $trip = null;
        if ($request->filled('trip_id') && auth()->user()->canAccessTrip((int) $request->input('trip_id'))) {
            $trip = Trip::find($request->input('trip_id'));
        }

        $result = $ocrService->scan(
            $request->file('receipt'),
            auth()->id(),
            $trip?->destination_currency
        );

        // Nothing readable on the receipt itself falls back to the trip's own
        // currency, which is what the traveller was most likely paying in.
        $result['currency'] = $result['currency'] ?? self::defaultCurrencyForTrip($trip);

        if (! auth()->user()->ocr_auto_categorize) {
            unset($result['category']);
        }

        return response()->json($result);
    }
}
