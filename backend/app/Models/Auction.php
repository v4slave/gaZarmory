<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
final class Auction extends Model
{
    protected $fillable=['treasury_item_id','quantity','starting_bid','minimum_step','ends_at','status','winner_player_id','winning_bid','created_by','finished_at','extension_minutes','extensions_count'];
    protected function casts():array{return['ends_at'=>'immutable_datetime','finished_at'=>'immutable_datetime'];}
    public function item():BelongsTo{return $this->belongsTo(TreasuryItem::class,'treasury_item_id');}
    public function bids():HasMany{return $this->hasMany(AuctionBid::class);}
    public function topBid():HasOne{return $this->hasOne(AuctionBid::class)->ofMany('amount','max');}
    public function winner():BelongsTo{return $this->belongsTo(Player::class,'winner_player_id');}
    public function autoBids():HasMany{return $this->hasMany(AuctionAutoBid::class);}
}
