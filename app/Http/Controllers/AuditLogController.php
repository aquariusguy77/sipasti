<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Pembacaan log audit (NFR3). Hanya-baca, karena pemicu basis data menolak
 * perintah ubah dan hapus.
 */
class AuditLogController extends Controller
{
    public function index(Request $request, AuditLogger $audit): View
    {
        $filters = $request->validate([
            'actor' => ['nullable', 'string', 'max:255'],
            'action' => ['nullable', 'string', 'max:255'],
            'entity' => ['nullable', 'string', 'max:255'],
            'entity_id' => ['nullable', 'string', 'max:255'],
        ]);

        $logs = AuditLog::query()
            ->when($filters['actor'] ?? null, fn ($q, $v) => $q->where('actor', 'like', "%{$v}%"))
            ->when($filters['action'] ?? null, fn ($q, $v) => $q->where('action', 'like', "{$v}%"))
            ->when($filters['entity'] ?? null, fn ($q, $v) => $q->where('entity', $v))
            ->when($filters['entity_id'] ?? null, fn ($q, $v) => $q->where('entity_id', $v))
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        // Pembacaan log audit juga dicatat, agar pengawas pun terawasi.
        $audit->read($request->user()->auditActor(), 'AuditLog');

        return view('audit.index', [
            'logs' => $logs,
            'filters' => $filters,
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
        ]);
    }
}
