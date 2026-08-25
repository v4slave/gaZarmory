<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MediaPost extends Model
{
    protected $fillable = ['user_id', 'title', 'description', 'kind', 'provider', 'source_url', 'embed_url', 'file_path', 'thumbnail_url'];
    protected $hidden = ['file_path'];

    public function author(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
    public function reactions(): HasMany { return $this->hasMany(MediaReaction::class); }
}
