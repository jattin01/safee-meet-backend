<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\AdminDashboardService;

class DashboardController extends Controller
{
    public function index(AdminDashboardService $dashboard)
    {
        return view('super-admin.dashboard', $dashboard->data());
    }
}
