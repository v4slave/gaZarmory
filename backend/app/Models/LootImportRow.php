<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
final class LootImportRow extends Model { protected $fillable=['loot_import_id','row_number','item_name','quantity','unit_price','status','raw_data']; protected function casts():array{return['raw_data'=>'array'];} }
