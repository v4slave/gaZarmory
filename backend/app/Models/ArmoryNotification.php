<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;use Illuminate\Database\Eloquent\Model;
final class ArmoryNotification extends Model {use HasUuids;protected $table='notifications';protected $fillable=['user_id','type','data','dedupe_key','read_at'];protected function casts():array{return['data'=>'array','read_at'=>'datetime'];}}
