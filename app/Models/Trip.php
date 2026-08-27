<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Trip extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id', 'destination', 'trip_name', 'start_date', 'end_date',
        'num_travelers', 'budget_limit', 'destination_currency', 'destination_budget', 'travel_type', 'status', 'notes',
        'cover_image', 'total_cost', 'summary_data', 'origin', 'origin_code', 'destination_code',
        'is_shared', 'share_code',
        'is_multi_city', 'leg2_destination', 'leg2_destination_code', 'leg2_start_date', 'leg2_end_date',
        'flight_selection', 'hotel_selection', 'venue_selection', 'attraction_selection',
        'leg2_flight_selection', 'leg2_hotel_selection', 'leg2_venue_selection', 'leg2_attraction_selection',
    ];

    // Generates an 8-char code from an alphabet without 0/O/1/I so it's
    // never ambiguous when a traveler reads it aloud or retypes it.
    public static function generateUniqueShareCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $code = collect(range(1, 8))
                ->map(fn () => $alphabet[random_int(0, strlen($alphabet) - 1)])
                ->implode('');
        } while (static::where('share_code', $code)->exists());

        return $code;
    }

    protected function casts(): array
    {
        return [
            'start_date'      => 'date',
            'end_date'        => 'date',
            'leg2_start_date' => 'date',
            'leg2_end_date'   => 'date',
            'budget_limit'        => 'decimal:2',
            'destination_budget'  => 'decimal:2',
            'summary_data'    => 'array',
            'is_shared'       => 'boolean',
            'is_multi_city'   => 'boolean',
            'flight_selection'          => 'array',
            'hotel_selection'           => 'array',
            'venue_selection'           => 'array',
            'attraction_selection'      => 'array',
            'leg2_flight_selection'     => 'array',
            'leg2_hotel_selection'      => 'array',
            'leg2_venue_selection'      => 'array',
            'leg2_attraction_selection' => 'array',
        ];
    }

    protected function resolvedStatus(): Attribute
    {
        return Attribute::make(get: function () {
            if ($stored = $this->getRawOriginal('status')) {
                return $stored;
            }

            $today = \Carbon\Carbon::today();
            if ($this->start_date->gt($today)) return 'upcoming';
            if ($this->end_date->lt($today))   return 'past';
            return 'active';
        });
    }

    public function user()        { return $this->belongsTo(User::class); }
    public function budgets()     { return $this->hasMany(TripBudget::class); }
    public function expenses()    { return $this->hasMany(Expense::class); }
    public function itinerary()   { return $this->hasMany(Itinerary::class); }
    public function savingsGoals(){ return $this->hasMany(SavingsGoal::class); }
    public function groupMembers(){ return $this->hasMany(GroupMember::class); }
    public function moments()     { return $this->hasMany(Moment::class); }
}
