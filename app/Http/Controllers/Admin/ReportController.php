<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OcrLog;
use App\Models\Review;
use App\Models\Trip;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index()
    {
        $active = 'reports';
        $recentTrips = Trip::with('user')->latest()->limit(5)->get();

        $stats = [
            'total_trips'         => Trip::count(),
            'gross_expenditures'  => $recentTrips->sum(fn (Trip $trip) => $trip->budget_limit ?? 0),
            'total_reviews'       => Review::count(),
            'ocr_success'         => OcrLog::where('status', 'success')->count(),
        ];

        $topDestinations = Trip::select('destination', DB::raw('count(*) as trip_count'))
            ->groupBy('destination')
            ->orderByDesc('trip_count')
            ->limit(5)
            ->get();

        // Each row pairs a recent trip with that same traveler's review for
        // the same destination, if they've left one — "Pending Check" when
        // they haven't, so admins can see which recent trips still have no
        // traveler feedback attached.
        $activity = $recentTrips->map(function (Trip $trip) {
            $review = Review::where('user_id', $trip->user_id)
                ->whereRaw('LOWER(destination) = LOWER(?)', [$trip->destination])
                ->where('status', 'active')
                ->first();

            return [
                'user'        => $trip->user->full_name ?? $trip->user->email ?? 'Traveler',
                'destination' => $trip->destination,
                'amount'      => $trip->budget_limit ?? 0,
                'rating'      => $review?->rating,
            ];
        });

        return view('admin.reports.index', compact('active', 'stats', 'topDestinations', 'activity'));
    }
}
