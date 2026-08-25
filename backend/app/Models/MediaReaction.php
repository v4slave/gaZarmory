<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MediaReaction extends Model
{
    protected $fillable = ['media_post_id', 'user_id', 'type'];
}
