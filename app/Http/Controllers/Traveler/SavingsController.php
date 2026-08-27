<?php
namespace App\Http\Controllers\Traveler;

use App\Http\Controllers\Controller;

class SavingsController extends Controller
{
    public function index()     { return view('traveler.savings.index'); }
    public function store()     { return back(); }
    public function addAmount() { return back(); }
}
