<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
final class LootImport extends Model { protected $fillable=['activity_id','created_by','source_type','original_filename','file_hash','status','error_message','confirmed_at']; protected function casts():array{return['confirmed_at'=>'immutable_datetime'];} public function rows():HasMany{return $this->hasMany(LootImportRow::class);} }
