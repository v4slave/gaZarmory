<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
final class Payout extends Model
{
    protected $fillable=['period_from','period_to','status','total_amount','calculated_at','paid_at','created_by'];protected function casts():array{return['period_from'=>'date','period_to'=>'date','calculated_at'=>'datetime','paid_at'=>'datetime'];}
    public function players(){return $this->hasMany(PayoutPlayer::class);}
    public function activities(){return $this->belongsToMany(Activity::class,'payout_activities');}
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
