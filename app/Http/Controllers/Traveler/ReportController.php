<?php
namespace App\Http\Controllers\Traveler;

use App\Http\Controllers\Controller;
use App\Models\Trip;
use App\Services\BudgetService;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ReportController extends Controller
{
    public function index(BudgetService $budgetService)
    {
        $trips = auth()->user()->trips()->with('budgets')->latest()->get();
        $summaries = $trips->mapWithKeys(fn($trip) => [
            $trip->id => $budgetService->summary($trip),
        ]);
        return view('traveler.reports.index', compact('trips', 'summaries'));
    }

    public function download(Request $request, ReportService $reportService)
    {
        $request->validate(['trip_id' => 'required|exists:trips,id']);
        $trip = Trip::findOrFail($request->trip_id);

        // Trip access, not ownership — Saved Trips lists trips shared with
        // you, so an owner-only check would 403 a companion the moment they
        // used the card's new download button.
        abort_if(!auth()->user()->canAccessTrip((int) $trip->id), 403);

        $pdf = $reportService->generatePdf($trip);
        $filename = 'budget-report-' . Str::slug($trip->destination) . '-' . now()->format('Y-m-d') . '.pdf';
        return $pdf->download($filename);
    }
}
