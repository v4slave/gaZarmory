<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
final class AuctionBid extends Model { public const UPDATED_AT=null; protected $fillable=['auction_id','player_id','amount']; public function player(){return $this->belongsTo(Player::class);} }
