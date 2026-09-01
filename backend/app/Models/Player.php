<?php

namespace App\Models;

use App\Enums\PlayerClass;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Player extends Model
{
    protected $fillable = ['nickname', 'class', 'is_active', 'group_id', 'gear_score', 'previous_gear_score', 'gear_score_updated_at', 'archa_gear_url', 'archa_gear_items', 'archa_gear_updated_at', 'character_render_path', 'has_ship', 'has_tank', 'has_fuchsias', 'has_clouds', 'has_machaon', 'has_tare', 'has_deer', 'has_invulnerable_pet', 'has_shield_swap', 'has_flippers'];
    protected $appends = ['character_render_url'];
    protected function casts(): array { return ['class' => PlayerClass::class, 'is_active' => 'boolean', 'gear_score' => 'integer', 'previous_gear_score' => 'integer', 'gear_score_updated_at' => 'datetime', 'archa_gear_items' => 'array', 'archa_gear_updated_at' => 'datetime', 'has_ship' => 'boolean', 'has_tank' => 'boolean', 'has_fuchsias' => 'boolean', 'has_clouds' => 'boolean', 'has_machaon' => 'boolean', 'has_tare' => 'boolean', 'has_deer' => 'boolean', 'has_invulnerable_pet' => 'boolean', 'has_shield_swap' => 'boolean', 'has_flippers' => 'boolean']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function group(): BelongsTo { return $this->belongsTo(GuildGroup::class, 'group_id'); }
    public function activities(): BelongsToMany { return $this->belongsToMany(Activity::class, 'activity_players')->withPivot('created_at'); }
    public function gearScoreHistory(): HasMany { return $this->hasMany(PlayerGearScoreHistory::class)->orderBy('recorded_at'); }
    public function getCharacterRenderUrlAttribute(): ?string { return $this->character_render_path ? asset('storage/'.$this->character_render_path) : null; }
}
