<?php
namespace App\Enums;

enum TicketStatus: string
{
    case OPEN = 'open';
    case IN_PROGRESS = 'in_progress';
    case CLOSED = 'closed';
    // Si piden agregar otro estado, solo agregar linea aqui
    case RESOLVED = 'resolved';

}
