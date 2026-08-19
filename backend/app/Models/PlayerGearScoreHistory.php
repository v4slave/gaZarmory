<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PlayerGearScoreHistory extends Model
{
    public $timestamps = false;
    protected $table = 'player_gear_score_history';
    protected $fillable = ['player_id', 'gear_score', 'recorded_at'];
    protected function casts(): array { return ['gear_score' => 'integer', 'recorded_at' => 'datetime']; }
    public function player(): BelongsTo { return $this->belongsTo(Player::class); }
}
