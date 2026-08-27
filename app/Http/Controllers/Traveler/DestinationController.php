<?php
namespace App\Http\Controllers\Traveler;

use App\Http\Controllers\Controller;
use App\Models\Destination;

class DestinationController extends Controller
{
    public function index()
    {
        return view('traveler.destinations.index');
    }

    public function show(Destination $destination)
    {
        $attractions = $destination->attractions()->orderBy('name')->get();
        return view('traveler.destinations.show', compact('destination', 'attractions'));
    }
}
