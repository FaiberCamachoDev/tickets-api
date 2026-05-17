<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard — {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet"/>
    <style>body { font-family: 'Figtree', sans-serif; }</style>
</head>
<body class="bg-gray-50 text-gray-800">

{{-- Nav --}}
<nav class="bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between">
    <div class="flex items-center gap-3">
        <div class="w-8 h-8 bg-indigo-600 rounded-lg flex items-center justify-center">
            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
        </div>
        <span class="font-semibold text-gray-900">{{ config('app.name') }}</span>
        <span class="text-gray-400">/</span>
        <span class="text-gray-600 text-sm">Dashboard</span>
    </div>
    <span class="text-xs text-gray-400">Actualizado: {{ now()->format('d/m/Y H:i') }}</span>
</nav>

<main class="max-w-7xl mx-auto px-6 py-8 space-y-8">

    {{-- Stat cards --}}
    <section>
        <h2 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-4">Resumen general</h2>
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
            @php
                $cards = [
                    ['label' => 'Tickets totales',    'value' => $summary['total_tickets'],     'color' => 'indigo'],
                    ['label' => 'Tickets abiertos',   'value' => $summary['open_tickets'],      'color' => 'amber'],
                    ['label' => 'Dispositivos',       'value' => $summary['total_devices'],     'color' => 'sky'],
                    ['label' => 'Disponibles',        'value' => $summary['available_devices'], 'color' => 'emerald'],
                    ['label' => 'Usuarios',           'value' => $summary['total_users'],       'color' => 'violet'],
                    ['label' => 'Actividad log',      'value' => $summary['total_logs'],        'color' => 'rose'],
                ];
                $colors = [
                    'indigo'  => 'bg-indigo-50 text-indigo-700 border-indigo-100',
                    'amber'   => 'bg-amber-50 text-amber-700 border-amber-100',
                    'sky'     => 'bg-sky-50 text-sky-700 border-sky-100',
                    'emerald' => 'bg-emerald-50 text-emerald-700 border-emerald-100',
                    'violet'  => 'bg-violet-50 text-violet-700 border-violet-100',
                    'rose'    => 'bg-rose-50 text-rose-700 border-rose-100',
                ];
            @endphp
            @foreach ($cards as $card)
                <div class="rounded-xl border p-4 {{ $colors[$card['color']] }}">
                    <p class="text-2xl font-bold">{{ $card['value'] }}</p>
                    <p class="text-xs mt-1 opacity-75">{{ $card['label'] }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Middle row: tickets by status + devices by status --}}
    <section class="grid md:grid-cols-2 gap-6">

        {{-- Tickets por status --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <h2 class="text-sm font-semibold text-gray-700 mb-4">Tickets por estado</h2>
            @php
                $statusColors = [
                    'open'        => ['bar' => 'bg-amber-400',   'badge' => 'bg-amber-100 text-amber-700'],
                    'in_progress' => ['bar' => 'bg-blue-400',    'badge' => 'bg-blue-100 text-blue-700'],
                    'resolved'    => ['bar' => 'bg-emerald-400', 'badge' => 'bg-emerald-100 text-emerald-700'],
                    'closed'      => ['bar' => 'bg-gray-400',    'badge' => 'bg-gray-100 text-gray-600'],
                ];
            @endphp
            <div class="space-y-3">
                @foreach ($ticketsByStatus as $row)
                    @php $c = $statusColors[$row['label']] ?? ['bar' => 'bg-gray-300', 'badge' => 'bg-gray-100 text-gray-600']; @endphp
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-xs px-2 py-0.5 rounded-full font-medium {{ $c['badge'] }}">
                                {{ str_replace('_', ' ', $row['label']) }}
                            </span>
                            <span class="text-xs text-gray-500 font-medium">{{ $row['count'] }} ({{ $row['percent'] }}%)</span>
                        </div>
                        <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full rounded-full {{ $c['bar'] }}" style="width: {{ $row['percent'] }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Devices por status --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <h2 class="text-sm font-semibold text-gray-700 mb-4">Dispositivos por estado</h2>
            @php
                $deviceColors = [
                    'available'   => 'bg-emerald-100 text-emerald-700',
                    'assigned'    => 'bg-blue-100 text-blue-700',
                    'maintenance' => 'bg-orange-100 text-orange-700',
                ];
            @endphp
            <div class="grid grid-cols-3 gap-4 h-full content-center">
                @foreach (['available', 'assigned', 'maintenance'] as $status)
                    @php $count = $devicesByStatus[$status]['count'] ?? 0; @endphp
                    <div class="text-center rounded-xl p-4 {{ $deviceColors[$status] ?? 'bg-gray-100 text-gray-600' }}">
                        <p class="text-3xl font-bold">{{ $count }}</p>
                        <p class="text-xs mt-1 capitalize font-medium">{{ $status }}</p>
                    </div>
                @endforeach
            </div>

            <div class="mt-6">
                <h3 class="text-xs font-semibold text-gray-500 mb-3">Tickets por prioridad</h3>
                @php
                    $priorityColors = [
                        'high'   => 'bg-red-100 text-red-700',
                        'medium' => 'bg-yellow-100 text-yellow-700',
                        'low'    => 'bg-green-100 text-green-700',
                    ];
                @endphp
                <div class="flex gap-3">
                    @foreach (['high', 'medium', 'low'] as $priority)
                        @php $count = $ticketsByPriority[$priority]['count'] ?? 0; @endphp
                        <div class="flex-1 text-center rounded-lg p-3 {{ $priorityColors[$priority] }}">
                            <p class="text-xl font-bold">{{ $count }}</p>
                            <p class="text-xs mt-0.5 capitalize font-medium">{{ $priority }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- Trend últimos 7 días --}}
    <section class="bg-white rounded-xl border border-gray-200 p-5">
        <h2 class="text-sm font-semibold text-gray-700 mb-4">Tickets creados — últimos 7 días</h2>
        @php $maxCount = $ticketTrend->max('count') ?: 1; @endphp
        <div class="flex items-end gap-2 h-24">
            @foreach ($ticketTrend as $day)
                @php $height = max(4, round(($day['count'] / $maxCount) * 100)); @endphp
                <div class="flex-1 flex flex-col items-center gap-1">
                    <span class="text-xs text-gray-500 font-medium">{{ $day['count'] ?: '' }}</span>
                    <div class="w-full rounded-t-md bg-indigo-400 transition-all"
                         style="height: {{ $height }}%"
                         title="{{ $day['date'] }}: {{ $day['count'] }} tickets"></div>
                    <span class="text-xs text-gray-400">{{ $day['date'] }}</span>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Recent activity --}}
    <section class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-700">Actividad reciente</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider">
                    <tr>
                        <th class="px-5 py-3 text-left">Acción</th>
                        <th class="px-5 py-3 text-left">Descripción</th>
                        <th class="px-5 py-3 text-left">Usuario</th>
                        <th class="px-5 py-3 text-left">IP</th>
                        <th class="px-5 py-3 text-left">Fecha</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse ($recentActivity as $log)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-5 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-50 text-indigo-700">
                                    {{ $log->action }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-gray-600 max-w-xs truncate">{{ $log->description }}</td>
                            <td class="px-5 py-3 text-gray-500">{{ $log->user?->name ?? 'Sistema' }}</td>
                            <td class="px-5 py-3 text-gray-400 font-mono text-xs">{{ $log->ip_address }}</td>
                            <td class="px-5 py-3 text-gray-400 text-xs whitespace-nowrap">
                                {{ $log->created_at->format('d/m H:i:s') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-8 text-center text-gray-400 text-sm">
                                Sin actividad registrada todavía.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

</main>

<footer class="max-w-7xl mx-auto px-6 py-4 text-xs text-gray-400 text-center">
    {{ config('app.name') }} · Entorno: <span class="font-medium">{{ config('app.env') }}</span>
</footer>

</body>
</html>
