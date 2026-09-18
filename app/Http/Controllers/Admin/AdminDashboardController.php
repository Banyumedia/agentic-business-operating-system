<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;

class AdminDashboardController extends Controller
{
    public function index()
    {
        $companies = Company::with('owner')->get();

        return view('admin.dashboard', compact('companies'));
    }
}
