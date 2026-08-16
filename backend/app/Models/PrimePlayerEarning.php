<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrimePlayerEarning extends Model
{
    public const UPDATED_AT = null;
    protected $fillable = ['activity_id', 'player_id', 'nickname_snapshot', 'prime_gold_value_snapshot', 'participants_count_snapshot', 'player_share', 'status', 'payout_id'];
    public function activity(){return $this->belongsTo(Activity::class);}
    public function payout(){return $this->belongsTo(Payout::class);}
}
