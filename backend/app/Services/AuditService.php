<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

final class AuditService
{
    public function __construct(private readonly Request $request) {}

    public function record(string $action, Model $entity, ?array $oldValues, ?array $newValues): void
    {
        AuditLog::query()->create([
            'user_id' => $this->request->user()?->id,
            'action' => $action, 'entity_type' => $entity->getMorphClass(), 'entity_id' => $entity->getKey(),
            'old_values' => $oldValues, 'new_values' => $newValues, 'ip_address' => $this->request->ip(),
        ]);
    }
}

