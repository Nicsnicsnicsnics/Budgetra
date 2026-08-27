<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class SettingsController extends Controller
{
    public function edit()
    {
        // Without 'active' the sidebar gets '' and no link highlights while
        // you're actually sitting on the settings page.
        return view('admin.settings.index', [
            'user'   => auth()->user(),
            'active' => 'settings',
        ]);
    }
}
