<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Activity extends Model
{
    protected $fillable = ['activity_definition_id', 'occurred_at', 'gold_value', 'created_by', 'completed_at'];
    protected function casts(): array { return ['occurred_at' => 'immutable_datetime', 'completed_at' => 'immutable_datetime', 'gold_value' => 'integer']; }
    public function definition(): BelongsTo { return $this->belongsTo(ActivityDefinition::class, 'activity_definition_id'); }
    public function players(): BelongsToMany { return $this->belongsToMany(Player::class, 'activity_players')->withPivot('created_at'); }
    public function loot(): HasMany { return $this->hasMany(ActivityLoot::class); }
    public function earnings(): HasMany { return $this->hasMany(PrimePlayerEarning::class); }
    public function lootImports(): HasMany { return $this->hasMany(LootImport::class)->latest('id'); }
    public function scopeCountedInStatistics(Builder $query): Builder
    {
        return $query->where(fn (Builder $activity) => $activity
            ->whereNotNull('completed_at')
            ->orWhereHas('earnings'));
    }
}
