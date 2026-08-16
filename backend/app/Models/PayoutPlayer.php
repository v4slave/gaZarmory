<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
final class PayoutPlayer extends Model { protected $fillable=['payout_id','player_id','nickname_snapshot','prime_attendance_percentage_snapshot','primes_count','mini_activities_count','amount','status','paid_at']; protected function casts():array{return['prime_attendance_percentage_snapshot'=>'decimal:2','amount'=>'integer','paid_at'=>'datetime'];} }
