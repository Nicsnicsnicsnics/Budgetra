<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    protected $fillable = [
        'trip_id', 'user_id', 'amount', 'amount_currency', 'amount_original', 'category',
        'description', 'receipt_path', 'expense_date',
    ];

    protected function casts(): array
    {
        return [
            // amount is ALWAYS pesos — the figure budgets and totals are measured
            // against. amount_original is what the traveller actually spent, in
            // amount_currency, kept so the peso value can be re-derived and so an
            // edit never re-converts an already-converted number.
            'amount'          => 'decimal:2',
            'amount_original' => 'decimal:2',
            'expense_date'    => 'date',
        ];
    }

    /** True when this expense was paid in something other than pesos. */
    public function isForeign(): bool
    {
        return $this->amount_currency !== null
            && $this->amount_currency !== 'PHP'
            && $this->amount_original !== null;
    }

    /** "¥3,500" for a foreign expense, "₱1,360" for a peso one. */
    public function originalAmountLabel(): string
    {
        if (! $this->isForeign()) {
            return '₱' . number_format((float) $this->amount, 2);
        }

        $symbol = \App\Support\PlaceCatalog::CURRENCY_SYMBOLS[$this->amount_currency]
            ?? $this->amount_currency . ' ';

        return $symbol . number_format((float) $this->amount_original, 2);
    }

    public function trip() { return $this->belongsTo(Trip::class); }
    public function user() { return $this->belongsTo(User::class); }
}
