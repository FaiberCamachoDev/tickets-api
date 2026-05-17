<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Device;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class MetricsService
{
    public function summary(): array
    {
        return [
            'total_tickets'      => Ticket::count(),
            'open_tickets'       => Ticket::where('status', 'open')->count(),
            'total_devices'      => Device::count(),
            'available_devices'  => Device::where('status', 'available')->count(),
            'total_users'        => User::count(),
            'total_logs'         => ActivityLog::count(),
        ];
    }

    public function ticketsByStatus(): Collection
    {
        $total = Ticket::count() ?: 1;

        return Ticket::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->orderByRaw('COUNT(*) DESC')
            ->get()
            ->map(fn ($row) => [
                'label'   => $row->status->value,
                'count'   => (int) $row->count,
                'percent' => round(((int) $row->count / $total) * 100),
            ]);
    }

    public function ticketsByPriority(): Collection
    {
        return Ticket::selectRaw('priority, COUNT(*) as count')
            ->groupBy('priority')
            ->orderByRaw('COUNT(*) DESC')
            ->get()
            ->keyBy('priority');
    }

    public function devicesByStatus(): Collection
    {
        return Device::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->orderByRaw('COUNT(*) DESC')
            ->get()
            ->keyBy('status');
    }

    // 7 consultas simples — compatible con SQL Server sin GROUP BY de fecha
    public function ticketTrend(): Collection
    {
        return collect(range(6, 0))->map(function (int $daysAgo) {
            $date = Carbon::today()->subDays($daysAgo);

            return [
                'date'  => $date->format('d/m'),
                'count' => Ticket::whereDate('created_at', $date)->count(),
            ];
        });
    }

    public function recentActivity(int $limit = 12): Collection
    {
        return ActivityLog::with('user')
            ->latest()
            ->take($limit)
            ->get();
    }
}
