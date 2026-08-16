<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
final class TreasuryItemTransaction extends Model
{
    public const UPDATED_AT = null;
    protected $fillable = ['treasury_item_id','type','quantity_delta','recipient_player_id','source_activity_id','auction_id','reason','created_by'];
    public function item(){return $this->belongsTo(TreasuryItem::class,'treasury_item_id');}
    public function recipient(){return $this->belongsTo(Player::class,'recipient_player_id');}
    public function sourceActivity(){return $this->belongsTo(Activity::class,'source_activity_id');}
    public function creator(){return $this->belongsTo(User::class,'created_by');}
}
