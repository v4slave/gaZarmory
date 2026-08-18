<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PlayerLinkRequest extends Model
{
    protected $fillable = ['user_id', 'player_id', 'status', 'reviewed_by', 'reviewed_at'];
    protected function casts(): array { return ['reviewed_at' => 'immutable_datetime']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function player(): BelongsTo { return $this->belongsTo(Player::class); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
}
