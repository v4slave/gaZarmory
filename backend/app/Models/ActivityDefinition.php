<?php

namespace App\Models;

use App\Enums\ActivityType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ActivityDefinition extends Model
{
    protected $fillable = ['name', 'type', 'is_active', 'icon_path'];
    protected $appends = ['icon_url'];
    protected function casts(): array { return ['type' => ActivityType::class, 'is_active' => 'boolean']; }
    public function activities(): HasMany { return $this->hasMany(Activity::class); }
    public function getIconUrlAttribute(): ?string { return $this->icon_path ? asset('storage/'.$this->icon_path) : null; }
}
