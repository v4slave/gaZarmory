<?php

namespace App\Models;

use App\Enums\PlayerClass;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Player extends Model
{
    protected $fillable = ['nickname', 'class', 'is_active', 'group_id'];
    protected function casts(): array { return ['class' => PlayerClass::class, 'is_active' => 'boolean']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function group(): BelongsTo { return $this->belongsTo(GuildGroup::class, 'group_id'); }
    public function activities(): BelongsToMany { return $this->belongsToMany(Activity::class, 'activity_players')->withPivot('created_at'); }
}

