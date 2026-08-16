<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;

final class AuditLogController extends Controller
{
    public function __invoke(Request $request): array
    {
        abort_unless($request->user()->canAdministrate(), 403);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'action' => ['nullable', 'string', 'max:120'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $logs = AuditLog::query()
            ->with('user:id,discord_username,discord_display_name')
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($nested) use ($search): void {
                    $nested->where('action', 'ilike', '%'.$search.'%')
                        ->orWhere('entity_type', 'ilike', '%'.$search.'%')
                        ->orWhereHas('user', fn ($user) => $user
                            ->where('discord_username', 'ilike', '%'.$search.'%')
                            ->orWhere('discord_display_name', 'ilike', '%'.$search.'%'));
                });
            })
            ->when($filters['action'] ?? null, fn ($query, $action) => $query->where('action', $action))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
            ->latest('id')
            ->paginate(25);

        return [
            'logs' => $logs,
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
        ];
    }
}
