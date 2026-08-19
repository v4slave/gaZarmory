<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
final class AuctionAutoBid extends Model { protected $fillable=['auction_id','player_id','max_amount'];protected function casts():array{return['max_amount'=>'integer'];}public function player(){return $this->belongsTo(Player::class);} }
