<?php

namespace App\Console\Commands;

use App\Models\ArmoryNotification;
use Illuminate\Console\Command;

final class PruneNotifications extends Command
{
    protected $signature = 'notifications:prune';

    protected $description = 'Delete notifications older than the configured retention period';

    public function handle(): int
    {
        $retentionDays = max(1, (int) config('notifications.retention_days', 7));
        $deleted = ArmoryNotification::query()
            ->where('created_at', '<', now()->subDays($retentionDays))
            ->delete();

        $this->info("Deleted {$deleted} expired notifications.");

        return self::SUCCESS;
    }
}
