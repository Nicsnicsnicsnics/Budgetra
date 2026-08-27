<?php
namespace App\Livewire\Traveler;

use App\Models\GroupMember;
use App\Models\Notification;
use App\Models\Trip;
use App\Models\User;
use App\Services\CurrencyConverterService;
use App\Services\TripImportService;
use App\Support\PlaceCatalog;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app', ['active' => 'saved-trips'])]
class SavedTrips extends Component
{
    public string $search       = '';
    public ?int $detailTripId   = null;
    public ?int $deleteTripId   = null;
    public string $deleteTripName = '';
    public ?int $editNameTripId  = null;
    public string $editNameValue = '';
    public string $editType      = 'Solo';
    public string $editStatus    = 'upcoming';
    public string $memberEmail   = '';
    public string $memberError   = '';
    public array  $pendingMembers = [];  // [['id'=>..,'name'=>..,'email'=>..]]
    public array  $savedMembers   = [];  // already saved for this trip
    public ?int $shareTripId    = null;
    public string $shareCode    = '';
    public string $shareLink    = '';
    public bool $shareNotAvailable = false;

    public function showDetail(int $id): void
    {
        $this->detailTripId = $this->detailTripId === $id ? null : $id;
    }

    public function closeDetail(): void
    {
        $this->detailTripId = null;
    }

    public function confirmDelete(int $id): void
    {
        $trip = Trip::find($id);
        $this->deleteTripId   = $id;
        $this->deleteTripName = $trip->trip_name ?? $trip->destination ?? 'this trip';
    }

    public function openEditName(int $id): void
    {
        $trip = Trip::find($id);
        // Owner-only: saveEditName() already refuses to write someone else's
        // trip, so opening the modal would only ever dead-end.
        if (!$trip || $trip->user_id !== auth()->id()) return;
        $this->editNameTripId = $id;
        $this->editNameValue = $trip->trip_name ?? $trip->destination ?? '';
        $this->editType      = strcasecmp($trip->travel_type ?? 'Solo', 'Solo') === 0 ? 'Solo' : 'Group';
        $today = \Carbon\Carbon::today();
        $computed = $trip->start_date->gt($today) ? 'upcoming' : ($trip->end_date->lt($today) ? 'past' : 'active');
        $this->editStatus     = $trip->getRawOriginal('status') ?? $computed;
        $this->memberEmail    = '';
        $this->memberError    = '';
        $this->pendingMembers = [];
        $this->savedMembers   = GroupMember::where('trip_id', $id)
            ->with('user')
            ->get()
            ->map(fn($m) => ['id' => $m->user_id, 'name' => $m->user->full_name, 'email' => $m->user->email])
            ->toArray();
    }

    public function lookupMember(): void
    {
        $this->memberError = '';
        $email = trim($this->memberEmail);
        if ($email === '') return;

        $user = User::where('email', $email)->first();
        if (!$user) {
            $this->memberError = 'No registered user found with that email.';
            return;
        }
        if ($user->id === auth()->id()) {
            $this->memberError = 'You are already the trip owner.';
            return;
        }
        $alreadyInSaved   = collect($this->savedMembers)->pluck('id')->contains($user->id);
        $alreadyInPending = collect($this->pendingMembers)->pluck('id')->contains($user->id);
        if ($alreadyInSaved || $alreadyInPending) {
            $this->memberError = 'This user is already added.';
            return;
        }
        $this->pendingMembers[] = ['id' => $user->id, 'name' => $user->full_name, 'email' => $user->email];
        $this->memberEmail = '';
    }

    public function removePendingMember(int $index): void
    {
        array_splice($this->pendingMembers, $index, 1);
    }

    public function removeSavedMember(int $userId): void
    {
        if (!$this->editNameTripId) return;
        GroupMember::where('trip_id', $this->editNameTripId)->where('user_id', $userId)->delete();
        $this->savedMembers = array_values(array_filter($this->savedMembers, fn($m) => $m['id'] !== $userId));
    }

    public function saveEditName(): void
    {
        if (!$this->editNameTripId || trim($this->editNameValue) === '') return;
        $trip = Trip::find($this->editNameTripId);
        if ($trip && $trip->user_id === auth()->id()) {
            $trip->update([
                'trip_name'   => trim($this->editNameValue),
                'travel_type' => $this->editType,
                'status'      => $this->editStatus,
            ]);
            $newName = trim($this->editNameValue) ?: $trip->destination;
            $trip->savingsGoals()->update(['goal_name' => $newName]);

            // Back to Solo means back to one traveller: drop the members and
            // reset the head count, otherwise the trip keeps splitting the
            // bill N ways and stays in those members' Saved Trips.
            if ($this->editType === 'Solo') {
                GroupMember::where('trip_id', $trip->id)->delete();
                $trip->update(['num_travelers' => 1]);
            }

            if ($this->editType === 'Group') {
                foreach ($this->pendingMembers as $m) {
                    $link = GroupMember::firstOrCreate(['trip_id' => $trip->id, 'user_id' => $m['id']]);
                    // Only on a genuinely new link — firstOrCreate returns the
                    // existing row when the member was already on the trip, and
                    // re-saving the modal shouldn't re-notify them.
                    if ($link->wasRecentlyCreated) {
                        $this->notifyAddedToTrip($trip, $m['id']);
                    }
                }
            }
        }
        $this->editNameTripId  = null;
        $this->editNameValue   = '';
        $this->editType        = 'Solo';
        $this->editStatus      = 'upcoming';
        $this->memberEmail     = '';
        $this->memberError     = '';
        $this->pendingMembers  = [];
        $this->savedMembers    = [];
    }

    public function cancelEditName(): void
    {
        $this->editNameTripId  = null;
        $this->editNameValue   = '';
        $this->editType        = 'Solo';
        $this->editStatus      = 'upcoming';
        $this->memberEmail     = '';
        $this->memberError     = '';
        $this->pendingMembers  = [];
        $this->savedMembers    = [];
    }

    public function cancelDelete(): void
    {
        $this->deleteTripId   = null;
        $this->deleteTripName = '';
    }

    public function openShare(int $id): void
    {
        $trip = Trip::find($id);
        if (!$trip || $trip->user_id !== auth()->id()) return;

        $importer = app(TripImportService::class);
        if (!$importer->isShareable($trip)) {
            $this->shareTripId       = $id;
            $this->shareNotAvailable = true;
            $this->shareCode         = '';
            $this->shareLink         = '';
            return;
        }

        $code = $importer->shareCodeFor($trip);
        $this->shareTripId       = $id;
        $this->shareCode         = $code;
        $this->shareLink         = route('trips.import', $code);
        $this->shareNotAvailable = false;
    }

    public function closeShareModal(): void
    {
        $this->shareTripId       = null;
        $this->shareCode         = '';
        $this->shareLink         = '';
        $this->shareNotAvailable = false;
    }

    public function deleteTrip(): void
    {
        if (!$this->deleteTripId) return;
        $trip = Trip::find($this->deleteTripId);
        if ($trip && $trip->user_id === auth()->id()) {
            $trip->delete();
        }
        if ($this->detailTripId === $this->deleteTripId) {
            $this->detailTripId = null;
        }
        $this->deleteTripId   = null;
        $this->deleteTripName = '';
    }

    /**
     * Tells a traveller they were added to someone else's trip. The trip then
     * shows up in their own Saved Trips, so the notification links to it.
     */
    private function notifyAddedToTrip(Trip $trip, int $memberId): void
    {
        $inviter = auth()->user()->full_name ?: 'A fellow traveler';
        $where   = $trip->trip_name ?: $trip->destination;

        Notification::create([
            'user_id' => $memberId,
            'trip_id' => $trip->id,
            'type'    => 'trip_shared',
            'message' => "{$inviter} added you to their trip to {$where}. It's now in your Saved Trips.",
            'is_read' => false,
        ]);
    }

    // Peso amount, formatted for display — in the trip's destination
    // currency once it's Upcoming or Ongoing and a live rate was found for
    // it, otherwise plain pesos. Deliberately NOT currency_code()/currency_symbol()
    // — that's a separate, unsynced account Settings field that defaults to
    // USD for every account regardless of the traveler's real currency, so
    // it was mislabeling genuine peso figures as "USD 141,106" and similar.
    public function displayAmount(Trip $trip, float $pesoAmount): string
    {
        if ($trip->display_rate) {
            return $trip->display_currency_code . ' ' . number_format($pesoAmount / $trip->display_rate, 0);
        }
        return '₱' . number_format($pesoAmount, 0);
    }

    private function fetchTrips()
    {
        $uid = auth()->id();

        // Trips the traveller owns, plus any they were added to as a group
        // member. A shared trip is only surfaced once it is a real plan —
        // someone else's unfinished draft is not something a member should
        // see in their list.
        return Trip::where(function ($q) use ($uid) {
                $q->where('user_id', $uid)
                  ->orWhere(fn ($m) => $m
                      ->whereHas('groupMembers', fn ($g) => $g->where('user_id', $uid))
                      ->where(fn ($d) => $d->whereNull('status')->orWhere('status', '!=', 'draft')));
            })
            // Matches either the traveller's own trip name or the destination,
            // since a renamed trip ("Barkada Getaway") no longer contains the
            // place it goes to, and searching for the place should still find it.
            ->when($this->search !== '', function ($q) {
                $term = '%' . str_replace('%', '\%', $this->search) . '%';
                $q->where(fn ($w) => $w->where('destination', 'ilike', $term)
                                       ->orWhere('trip_name', 'ilike', $term)
                                       ->orWhere('leg2_destination', 'ilike', $term));
            })
            ->withSum('expenses', 'amount')
            ->withCount('groupMembers')
            ->with('user:id,full_name')
            ->latest('created_at')
            ->get()
            ->map(function (Trip $trip) {
                $today = Carbon::today();
                $days  = max(1, (int) $trip->start_date->diffInDays($trip->end_date));
                $trip->setAttribute('days', $days);
                $trip->setAttribute('status',
                    $trip->status ??
                    ($trip->start_date->gt($today) ? 'upcoming' :
                    ($trip->end_date->lt($today)   ? 'past'     : 'active')));

                // Real spending from logged Expenses, vs. the planned budget —
                // lets the card show actual money tracking, not just the estimate.
                $actualSpent = (float) ($trip->expenses_sum_amount ?? 0);
                $budget      = (float) ($trip->total_cost ?? $trip->budget_limit ?? 0);
                $trip->setAttribute('actual_spent', $actualSpent);
                $trip->setAttribute('spend_pct', $budget > 0 ? min(100, round($actualSpent / $budget * 100)) : 0);

                // Split-bill figures. Head count is the trip's own
                // num_travelers, floored at 1 so a malformed row can't divide
                // by zero and at the real member count when more people were
                // added than the planner originally allowed for.
                // Solo trips are always one head no matter what num_travelers
                // happens to hold — a trip switched Group→Solo can be left
                // carrying the old count.
                $isGroup = strcasecmp($trip->travel_type ?? 'Solo', 'Group') === 0;
                $heads   = $isGroup
                    ? max(1, (int) $trip->num_travelers, $trip->group_members_count + 1)
                    : 1;
                $trip->setAttribute('head_count', $heads);
                $trip->setAttribute('cost_per_person',  $budget      / $heads);
                $trip->setAttribute('spent_per_person', $actualSpent / $heads);

                $trip->setAttribute('shared_with_me', $trip->user_id !== auth()->id());

                // Once a trip is Upcoming or Ongoing, money is more useful
                // shown in the destination's own currency than in pesos —
                // but only when a live rate is actually reachable; a failed
                // lookup silently falls back to the usual peso display
                // rather than blocking the page or showing an error on a
                // passive card. Draft and Past trips stay in pesos.
                $trip->setAttribute('display_currency_code', null);
                $trip->setAttribute('display_currency_symbol', null);
                $trip->setAttribute('display_rate', null);
                if (in_array($trip->status, ['active', 'upcoming'], true) && $trip->destination_currency) {
                    $liveRate = (new CurrencyConverterService())->rateToPhp($trip->destination_currency);
                    if ($liveRate !== null) {
                        $trip->setAttribute('display_currency_code', $trip->destination_currency);
                        $trip->setAttribute('display_currency_symbol', PlaceCatalog::CURRENCY_SYMBOLS[$trip->destination_currency] ?? $trip->destination_currency);
                        $trip->setAttribute('display_rate', $liveRate);
                    }
                }

                return $trip;
            })
            // Two trips that share the same start date most often means one
            // is a single-leg duplicate of a multi-city trip's own leg (e.g.
            // a separate "Davao City" card alongside "Cebu City & Davao
            // City" that both start Aug 24) — put the multi-city trip first
            // in that case instead of leaving it to arrival order, whether
            // the flights involved were one-way or round-trip.
            ->sort(function (Trip $a, Trip $b) {
                if (!$a->start_date->eq($b->start_date)) return 0;
                return ($b->is_multi_city <=> $a->is_multi_city);
            })
            ->values();
    }

    public function render()
    {
        $trips = $this->fetchTrips();
        $detailTrip = $this->detailTripId
            ? $trips->firstWhere('id', $this->detailTripId)
            : null;
        return view('livewire.traveler.saved-trips', compact('trips', 'detailTrip'));
    }
}
