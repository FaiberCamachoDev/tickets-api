<?php

namespace App\Http\Controllers;

use App\Services\MetricsService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly MetricsService $metrics) {}

    public function index(): View
    {
        return view('dashboard.index', [
            'summary'          => $this->metrics->summary(),
            'ticketsByStatus'  => $this->metrics->ticketsByStatus(),
            'ticketsByPriority'=> $this->metrics->ticketsByPriority(),
            'devicesByStatus'  => $this->metrics->devicesByStatus(),
            'ticketTrend'      => $this->metrics->ticketTrend(),
            'recentActivity'   => $this->metrics->recentActivity(),
        ]);
    }
}
