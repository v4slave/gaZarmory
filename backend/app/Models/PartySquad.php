<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class PartySquad extends Model
{
    protected $fillable = ['group_id', 'name', 'position'];
    public function group(): BelongsTo { return $this->belongsTo(GuildGroup::class, 'group_id'); }
    public function players(): BelongsToMany { return $this->belongsToMany(Player::class, 'party_squad_players')->withTimestamps(); }
}
