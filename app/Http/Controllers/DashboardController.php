<?php

namespace App\Http\Controllers;

use App\Services\DashboardStats;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardStats $stats): View
    {
        return view('dashboard', [
            'stats' => $stats->for($request->user()),
            'greeting' => DashboardStats::greeting(),
        ]);
    }
}
