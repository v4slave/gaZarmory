<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
final class TreasuryItem extends Model
{
    protected $fillable=['item_name','quantity','reserved_quantity','unit_value','icon_path'];
    protected $appends=['icon_url','available_quantity'];
    public function getIconUrlAttribute():?string{return $this->icon_path?asset('storage/'.$this->icon_path):null;}
    public function getAvailableQuantityAttribute():int{return $this->quantity-$this->reserved_quantity;}
}
