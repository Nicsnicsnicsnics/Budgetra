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

    private static function currencyCodes(): array
    {
        return array_keys(PlaceCatalog::CURRENCY_SYMBOLS);
    }

    private function resolveAmountInPesos(array $validated): array
    {
        $code  = strtoupper((string) ($validated['amount_currency'] ?? 'PHP')) ?: 'PHP';
        $typed = (float) $validated['amount'];

        if ($code === 'PHP') {
            $validated['amount_currency'] = 'PHP';
            $validated['amount_original'] = null;

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

    public static function defaultCurrencyForTrip(?Trip $trip): string
    {
        return $trip?->destination_currency ?: 'PHP';
    }

    public function index(Request $request)
    {
        $user  = auth()->user();

        $trips = $user->accessibleTrips()->latest()->get()
            ->filter(fn ($t) => in_array($t->resolved_status, ['active', 'upcoming', 'past'], true))
            ->values();

        $accessibleIds = $user->accessibleTrips()->pluck('id');
        $query = Expense::with(['trip', 'user:id,full_name'])
            ->whereIn('trip_id', $accessibleIds)
            ->latest('expense_date');

        $tripId = $request->filled('trip_id') ? $request->trip_id : $trips->first()?->id;

        if ($tripId)                       $query->where('trip_id', $tripId);
        if ($request->filled('category'))  $query->where('category', $request->category);

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

        $preselected = $request->filled('trip_id')
            ? $trips->firstWhere('id', (int) $request->input('trip_id'))
            : $trips->first();

        $defaultCurrency = self::defaultCurrencyForTrip($preselected);

        return view('traveler.expenses.create', compact('trips', 'categories', 'defaultCurrency'));
    }

    private function normaliseAmount(Request $request): void
    {
        $amount = $request->input('amount');
        if (is_string($amount)) {
            $request->merge(['amount' => str_replace(',', '', $amount)]);
        }
    }

    public function store(Request $request)
    {
        $this->normaliseAmount($request);

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
        abort_if(!auth()->user()->canAccessTrip((int) $expense->trip_id), 403);
        $trips      = auth()->user()->accessibleTrips()->latest()->get();
        $categories = self::CATEGORIES;
        return view('traveler.expenses.edit', compact('expense', 'trips', 'categories'));
    }

    public function update(Request $request, Expense $expense)
    {
        abort_if(!auth()->user()->canAccessTrip((int) $expense->trip_id), 403);

        $this->normaliseAmount($request);

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

        try {
            $validated = $this->resolveAmountInPesos($validated);
        } catch (CurrencyUnavailable $e) {
            return back()->withInput()->withErrors(['amount' => $e->getMessage()]);
        }

        $oldReceiptPath = $expense->receipt_path;
        $replacingReceipt = $request->hasFile('receipt');

        if ($replacingReceipt) {
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

        if ($replacingReceipt && $oldReceiptPath) {
            Storage::disk('public')->delete($oldReceiptPath);
        }

        return redirect()->route('expenses.index')->with('success', 'Expense updated.');
    }

    public function destroy(Expense $expense)
    {
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

        $trip = null;
        if ($request->filled('trip_id') && auth()->user()->canAccessTrip((int) $request->input('trip_id'))) {
            $trip = Trip::find($request->input('trip_id'));
        }

        $result = $ocrService->scan(
            $request->file('receipt'),
            auth()->id(),
            $trip?->destination_currency
        );

        $result['currency'] = $result['currency'] ?? self::defaultCurrencyForTrip($trip);

        if (! auth()->user()->ocr_auto_categorize) {
            unset($result['category']);
        }

        return response()->json($result);
    }
}
