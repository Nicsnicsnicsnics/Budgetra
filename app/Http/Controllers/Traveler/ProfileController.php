<?php
namespace App\Http\Controllers\Traveler;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function edit()
    {
        $user = auth()->user();
        $profile = $user->userProfile;

        // A traveler who has not been through the builder has nothing to show
        // on the right-hand half of this page, so they are sent to it instead.
        //
        // The check has to live here rather than on the sidebar's Profile
        // href: that sidebar is @persist'ed, so wire:navigate freezes whatever
        // the href was at page load, and a traveler who finished the builder
        // would keep being thrown back into it. Re-deciding per request is
        // what stops that.
        //
        // Deliberately without ?return=, which is the quick-edit path used by
        // the per-section Edit links below — it replaces Next Step with a lone
        // Save Changes. Someone starting from nothing needs all seven steps.
        if (! $profile) {
            return redirect()->route('profile.setup');
        }

        // The country lookup that used to live here as a loop over
        // config('country_cities') now sits in PlaceCatalog::countryFor(), which
        // also knows the 154 destinations that config never listed. The view
        // calls it directly, so nothing needs passing down.
        return view('traveler.profile.edit', [
            'user'    => $user,
            'profile' => $profile,
        ]);
    }

    public function update(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'first_name'     => 'required|string|max:100',
            'last_name'      => 'required|string|max:100',
            'profile_photo'  => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        // build full_name from parts, preserving any existing middle name
        $validated['full_name'] = trim(
            $validated['first_name'] . ' ' .
            ($user->middle_name ? $user->middle_name . ' ' : '') .
            $validated['last_name']
        );

        if ($request->hasFile('profile_photo')) {
            if ($user->profile_photo) {
                Storage::disk('public')->delete($user->profile_photo);
            }
            $validated['profile_photo'] = $request->file('profile_photo')
                ->store('profile-photos', 'public');
        } else {
            unset($validated['profile_photo']);
        }

        $user->update($validated);

        return back()->with('success', 'Profile updated successfully.');
    }
}
