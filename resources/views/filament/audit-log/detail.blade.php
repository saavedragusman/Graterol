{{-- S8/C15: detalle de solo lectura; todo valor se escapa con {{ }}. --}}
<div class="space-y-4 text-sm">
    <dl class="grid grid-cols-2 gap-2">
        <div><dt class="font-semibold">Fecha y hora</dt><dd>{{ $log->created_at?->timezone(\App\Support\DisplayTimezone::CARACAS)->format('d/m/Y H:i') }}</dd></div>
        <div><dt class="font-semibold">Usuario</dt><dd>{{ $log->user?->name ?? 'Sistema' }}</dd></div>
        <div><dt class="font-semibold">Tipo</dt><dd>{{ \App\Filament\Resources\AuditLogResource::typeLabel($log->auditable_type) }}</dd></div>
        <div><dt class="font-semibold">Referencia</dt><dd>{{ \App\Filament\Resources\AuditLogResource::reference($log) }}</dd></div>
        <div><dt class="font-semibold">Acción</dt><dd>{{ \App\Filament\Resources\AuditLogResource::actionLabel($log->action) }}</dd></div>
        <div><dt class="font-semibold">Motivo</dt><dd>{{ $log->reason ?? '—' }}</dd></div>
    </dl>

    <table class="w-full text-left">
        <thead>
            <tr>
                <th class="py-1 pe-2">Campo</th>
                <th class="py-1 pe-2">Antes</th>
                <th class="py-1">Después</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr class="align-top">
                    <td class="py-1 pe-2 font-medium">{{ $row['field'] }}</td>
                    <td class="py-1 pe-2"><pre class="whitespace-pre-wrap font-sans">{{ $row['before'] }}</pre></td>
                    <td class="py-1"><pre class="whitespace-pre-wrap font-sans">{{ $row['after'] }}</pre></td>
                </tr>
            @empty
                <tr><td colspan="3" class="py-1">Sin valores registrados.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
