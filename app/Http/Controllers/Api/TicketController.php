<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\Ticket\StoreTicketRequest;
use App\Http\Requests\Api\Ticket\UpdateTicketRequest;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Services\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketController extends ApiController
{
    public function __construct(private readonly TicketService $ticketService) {}

    public function index(Request $request): JsonResponse
    {
        $tickets = $this->ticketService->list($request->user(), $request->only(['status', 'priority', 'category']));

        return $this->paginated($tickets, 'OK', fn ($items) => TicketResource::collection($items)->resolve());
    }

    public function show(Request $request, Ticket $ticket): JsonResponse
    {
        if ($ticket->user_id !== $request->user()->id) {
            return $this->error('No tienes permiso para ver este ticket', 403);
        }

        return $this->success(new TicketResource($ticket->load(['user', 'device'])));
    }

    public function store(StoreTicketRequest $request): JsonResponse
    {
        $ticket = $this->ticketService->create($request->user(), $request->validated());

        return $this->success(new TicketResource($ticket), 'Ticket creado correctamente', 201);
    }

    public function update(UpdateTicketRequest $request, Ticket $ticket): JsonResponse
    {
        if ($ticket->user_id !== $request->user()->id) {
            return $this->error('No tienes permiso para modificar este ticket', 403);
        }

        $ticket = $this->ticketService->update($ticket, $request->validated());

        return $this->success(new TicketResource($ticket), 'Ticket actualizado correctamente');
    }

    public function destroy(Request $request, Ticket $ticket): JsonResponse
    {
        if ($ticket->user_id !== $request->user()->id) {
            return $this->error('No tienes permiso para eliminar este ticket', 403);
        }

        $this->ticketService->delete($ticket);

        return $this->success(null, 'Ticket eliminado correctamente');
    }
}
