<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'full_name', 'first_name', 'middle_name', 'last_name', 'contact_number',
        'email', 'password', 'phone', 'country',
        'currency_code', 'currency_symbol', 'role', 'profile_photo', 'theme',
        'default_buffer_pct', 'notify_budget_alerts', 'notify_trip_reminders',
        'notify_itinerary_reminders', 'ocr_auto_categorize',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password'                    => 'hashed',
            'notify_budget_alerts'        => 'boolean',
            'notify_trip_reminders'       => 'boolean',
            'notify_itinerary_reminders'  => 'boolean',
            'ocr_auto_categorize'         => 'boolean',
            'password_changed_at'         => 'datetime',
        ];
    }

    public function trips()       { return $this->hasMany(Trip::class); }
    public function expenses()    { return $this->hasMany(Expense::class); }
    public function savingsGoals(){ return $this->hasMany(SavingsGoal::class); }
    public function notifications(){ return $this->hasMany(Notification::class); }
    public function reviews()     { return $this->hasMany(Review::class); }
    public function ocrLogs()     { return $this->hasMany(OcrLog::class); }
    public function userProfile() { return $this->hasOne(UserProfile::class); }

    /**
     * Every trip this traveller can see: the ones they own, plus any they were
     * added to as a group member.
     *
     * trips() stays owner-only — it backs ownership checks and admin counts.
     * This is the scope the traveller-facing pages use, so someone added to a
     * group trip isn't met with an empty state on every tab just because they
     * haven't planned a trip of their own yet.
     *
     * Someone else's unfinished draft is excluded: a member should only see a
     * shared trip once it's a real plan.
     */
    public function accessibleTrips(): \Illuminate\Database\Eloquent\Builder
    {
        return Trip::where(function ($q) {
            $q->where('user_id', $this->id)
              ->orWhere(fn ($m) => $m
                  ->whereHas('groupMembers', fn ($g) => $g->where('user_id', $this->id))
                  ->where(fn ($d) => $d->whereNull('status')->orWhere('status', '!=', 'draft')));
        });
    }

    /** True when the traveller can open a given trip (owner or member). */
    public function canAccessTrip(int $tripId): bool
    {
        return $this->accessibleTrips()->whereKey($tripId)->exists();
    }
}
