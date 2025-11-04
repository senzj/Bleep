<?php

namespace App\Http\Controllers;

use App\Models\Bleep;
use Illuminate\Http\Request;

class BleepController extends Controller
{
    public function index()
    {
        $bleeps = Bleep::with('user')
            ->latest()
            ->take(50)
            ->get();

        return view('home', ['bleeps' => $bleeps]);
    }
}
