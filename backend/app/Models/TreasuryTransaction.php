<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
final class TreasuryTransaction extends Model
{
    public const UPDATED_AT = null;
    protected $fillable = ['type','amount','balance_after','description','related_entity_type','related_entity_id','created_by'];
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
