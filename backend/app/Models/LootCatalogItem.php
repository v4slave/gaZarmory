<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
final class LootCatalogItem extends Model
{
    protected $fillable=['name','icon_path','rarity','is_active'];protected $appends=['icon_url'];protected function casts():array{return['is_active'=>'boolean'];}
    public function getIconUrlAttribute():?string{return $this->icon_path?asset('storage/'.$this->icon_path).'#rarity-'.($this->rarity?:'common'):null;}
}
