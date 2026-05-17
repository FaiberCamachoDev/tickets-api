<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Models\ActivityLog;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TicketService
{
    public function list(User $user, array $filters = []): LengthAwarePaginator
    {
        return Ticket::with(['user', 'device'])
            ->where('user_id', $user->id)
            ->when(isset($filters['status']),   fn ($q) => $q->where('status', $filters['status']))
            ->when(isset($filters['priority']), fn ($q) => $q->where('priority', $filters['priority']))
            ->when(isset($filters['category']), fn ($q) => $q->where('category', $filters['category']))
            ->latest()
            ->paginate(15);
    }

    public function create(User $user, array $data): Ticket
    {
        $ticket = $user->tickets()->create(array_merge($data, [
            'status' => TicketStatus::OPEN,
        ]));

        ActivityLog::create([
            'user_id'       => $user->id,
            'action'        => 'ticket_created',
            'loggable_type' => Ticket::class,
            'loggable_id'   => $ticket->id,
            'description'   => "Ticket '{$ticket->title}' creado",
            'ip_address'    => request()->ip(),
            'user_agent'    => request()->userAgent(),
        ]);

        return $ticket->load(['user', 'device']);
    }

    public function update(Ticket $ticket, array $data): Ticket
    {
        $ticket->update($data);

        ActivityLog::create([
            'user_id'       => $ticket->user_id,
            'action'        => 'ticket_updated',
            'loggable_type' => Ticket::class,
            'loggable_id'   => $ticket->id,
            'description'   => "Ticket '{$ticket->title}' actualizado",
            'ip_address'    => request()->ip(),
            'user_agent'    => request()->userAgent(),
            'metadata'      => ['changed_fields' => array_keys($data)],
        ]);

        return $ticket->fresh(['user', 'device']);
    }

    public function delete(Ticket $ticket): void
    {
        ActivityLog::create([
            'user_id'       => $ticket->user_id,
            'action'        => 'ticket_deleted',
            'loggable_type' => Ticket::class,
            'loggable_id'   => $ticket->id,
            'description'   => "Ticket '{$ticket->title}' eliminado (soft delete)",
            'ip_address'    => request()->ip(),
            'user_agent'    => request()->userAgent(),
        ]);

        $ticket->delete();
    }
}
