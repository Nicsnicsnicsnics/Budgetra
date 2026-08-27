<?php
namespace App\Http\Controllers\Traveler;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function edit()
    {
        return view('traveler.settings.index', ['user' => auth()->user()]);
    }

    public function updateTheme(Request $request)
    {
        $validated = $request->validate([
            'theme' => 'required|in:daylight,nightflight,terracotta,retro-wanderlust,sakura-bloom,original,auto',
        ]);

        auth()->user()->update($validated);

        if ($request->wantsJson()) {
            return response()->json(['theme' => $validated['theme']]);
        }

        return back()->with('success', 'Theme updated.');
    }

    public function updatePreferences(Request $request)
    {
        $validated = $request->validate([
            'currency_code'      => 'required|string|size:3',
            'currency_symbol'    => 'required|string|max:5',
            'default_buffer_pct' => 'required|integer|min:0|max:100',
        ]);

        auth()->user()->update($validated);

        if ($request->wantsJson()) {
            return response()->json($validated);
        }

        return back()->with('success', 'Preferences updated.');
    }

    public function updateNotifications(Request $request)
    {
        $validated = $request->validate([
            'field' => 'required|in:notify_budget_alerts,notify_trip_reminders,notify_itinerary_reminders,ocr_auto_categorize',
            'value' => 'required|boolean',
        ]);

        auth()->user()->update([$validated['field'] => $validated['value']]);

        return response()->json(['field' => $validated['field'], 'value' => $validated['value']]);
    }
}
