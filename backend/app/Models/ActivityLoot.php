<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLoot extends Model
{
    protected $table = 'activity_loot';
    protected $fillable = ['activity_id', 'loot_catalog_item_id', 'item_name', 'quantity', 'unit_price', 'icon_path', 'rarity', 'created_by'];
    protected $appends = ['icon_url', 'total_price'];

    public function getIconUrlAttribute(): ?string
    {
        return $this->icon_path ? asset('storage/'.$this->icon_path).'#rarity-'.($this->rarity ?: 'common') : null;
    }

    public function getTotalPriceAttribute(): int
    {
        return $this->quantity * $this->unit_price;
    }
}
