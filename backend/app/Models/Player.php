<?php

namespace App\Models;

use App\Enums\PlayerClass;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Player extends Model
{
    protected $fillable = ['nickname', 'class', 'is_active', 'group_id', 'gear_score', 'previous_gear_score', 'gear_score_updated_at', 'has_ship', 'has_tank', 'has_fuchsias', 'has_clouds', 'has_machaon', 'has_tare', 'has_deer', 'has_invulnerable_pet', 'has_shield_swap', 'has_flippers'];
    protected function casts(): array { return ['class' => PlayerClass::class, 'is_active' => 'boolean', 'gear_score' => 'integer', 'previous_gear_score' => 'integer', 'gear_score_updated_at' => 'datetime', 'has_ship' => 'boolean', 'has_tank' => 'boolean', 'has_fuchsias' => 'boolean', 'has_clouds' => 'boolean', 'has_machaon' => 'boolean', 'has_tare' => 'boolean', 'has_deer' => 'boolean', 'has_invulnerable_pet' => 'boolean', 'has_shield_swap' => 'boolean', 'has_flippers' => 'boolean']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function group(): BelongsTo { return $this->belongsTo(GuildGroup::class, 'group_id'); }
    public function activities(): BelongsToMany { return $this->belongsToMany(Activity::class, 'activity_players')->withPivot('created_at'); }
}
