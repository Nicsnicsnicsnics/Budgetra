<?php
namespace App\Http\Controllers\Traveler;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Trip;

class AlertController extends Controller
{
    public function index()
    {
        $user  = auth()->user();
        // "!= 'draft'" alone silently drops any trip with a NULL status too
        // (SQL's three-valued logic: NULL != 'draft' is unknown, not true) —
        // a real, non-draft trip that predates a status default being set
        // would vanish from this list and wrongly trigger the "no trips"
        // empty state. Explicitly keep NULL-status trips as well.
        $trips = $user->trips()
            ->where(fn ($q) => $q->where('status', '!=', 'draft')->orWhereNull('status'))
            ->orderByDesc('start_date')
            ->get();

        // Every notification the traveler has, across every trip and including
        // the ones with no trip at all. Two things used to hide notifications
        // here: narrowing to a single "active" trip (the view has no trip
        // picker, so that filter was invisible and permanently on), and
        // blanking the list for anyone who owned no trips — which hid the very
        // notification telling a new traveller they'd been added to one.
        $notifications = $user->notifications()->with('trip')->latest()->paginate(20);

        return view('traveler.alerts.index', compact('notifications', 'trips'));
    }

    public function markRead(Notification $notification)
    {
        abort_if($notification->user_id !== auth()->id(), 403);
        $notification->update(['is_read' => true]);
        return back();
    }

    public function markAllRead()
    {
        auth()->user()->notifications()->where('is_read', false)->update(['is_read' => true]);
        return back();
    }
}
