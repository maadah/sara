<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /**
     * Display reports page
     */
    public function index()
    {
        return view('customer.reports.index');
    }
}
