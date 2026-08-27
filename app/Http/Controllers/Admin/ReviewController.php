<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class ReviewController extends Controller
{
    public function index()        { return view('admin.reviews.index'); }
    public function updateStatus() { return back(); }
    public function destroy()      { return back(); }
}
