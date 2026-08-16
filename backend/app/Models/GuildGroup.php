<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GuildGroup extends Model
{
    protected $table = 'groups';
    protected $fillable = ['name'];
    public function players(): HasMany { return $this->hasMany(Player::class, 'group_id'); }
}

