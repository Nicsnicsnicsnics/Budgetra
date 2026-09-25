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
        'num_travelers', 'budget_limit', 'budget_currency', 'budget_local',
        'destination_currency', 'travel_type', 'status', 'notes',
        'cover_image', 'total_cost', 'summary_data', 'origin', 'origin_code', 'destination_code',
        'is_shared', 'share_code',
        'is_multi_city', 'leg2_destination', 'leg2_destination_code', 'leg2_start_date', 'leg2_end_date',
        'flight_selection', 'hotel_selection', 'venue_selection', 'attraction_selection',
        'leg2_flight_selection', 'leg2_hotel_selection', 'leg2_venue_selection', 'leg2_attraction_selection',
    ];

    /**
     * Scenery shown on a trip card that has no photo of its own.
     *
     * Deliberately no hotels, inns or apartments: the whole reason a trip
     * lands here is that no accommodation was picked, so a hotel lobby would
     * claim something the trip does not have.
     */
    public const FALLBACK_COVERS = [
        'beach.jpg', 'international 1.jpg', 'nature.jpg', 'international 2.jpg',
        'adventure.jpg', 'international 3.jpg', 'historical.jpg', 'international 4.jpg',
        'relaxation.jpg', 'international 5.jpg', 'resort.jpg', 'international 6.jpg',
        'museums.jpg', 'international 7.jpg', 'nightlife.jpg', 'international 8.jpg',
        'shopping.jpg', 'international 9.jpg', 'foodtrip.jpg', 'international 10.jpg',
    ];

    /**
     * The picture for this trip's card.
     *
     * cover_image is whatever the wizard managed to capture — the chosen
     * hotel's photo, or failing that the first attraction's. A trip planned
     * without accommodation has neither, and the card fell through to a bare
     * gradient that read as a half-loaded image rather than a design.
     *
     * The stand-in is chosen by trip id, not at random. A genuine random pick
     * would hand the same card a different photo on every Livewire refresh,
     * so a board would reshuffle itself while you were looking at it. Keying
     * off the id also means one trip looks the same on Saved Trips, the Hub
     * and its savings goal, and that neighbouring trips — consecutive ids —
     * never draw the same picture.
     */
    public function coverImageUrl(): string
    {
        if ($this->cover_image) {
            return $this->cover_image;
        }

        // Drafts keep their own deliberately indistinct image; the card blurs
        // itself on top of it.
        if ($this->status === 'draft') {
            return asset('stockimages/draftimage.jpg');
        }

        $pool = self::FALLBACK_COVERS;
        $file = $pool[abs((int) $this->id) % count($pool)];

        // Several of these filenames carry a space.
        return asset('stockimages/' . rawurlencode($file));
    }

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
            // budget_limit is pesos; budget_local is what the traveller typed,
            // in budget_currency (their registration country's currency).
            'budget_limit'        => 'decimal:2',
            'budget_local'        => 'decimal:2',
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
