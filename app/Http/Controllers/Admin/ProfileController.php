<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function edit()
    {
        return view('admin.profile.edit', [
            'user'   => auth()->user(),
            'active' => 'profile',
        ]);
    }

    public function update(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'first_name'    => 'required|string|max:100',
            'last_name'     => 'required|string|max:100',
            'profile_photo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        // Rebuild full_name from the parts, keeping any middle name the
        // account already had — it isn't editable on this form.
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
            // Without this the key would still be present as null and wipe
            // the existing photo on any save that doesn't attach a new one.
            unset($validated['profile_photo']);
        }

        $user->update($validated);

        return back()->with('success', 'Profile updated.');
    }
}
