<?php
namespace App\Actions;
use App\Models\ActivityLoot;
use App\Models\Activity;
use App\Models\LootImport;
use App\Models\TreasuryItem;
use App\Models\TreasuryItemTransaction;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ConfirmLootImport
{
    public function __construct(private readonly AuditService $audit){}
    public function execute(LootImport $import,int $userId):LootImport
    {
        return DB::transaction(function()use($import,$userId){
            $locked=LootImport::query()->lockForUpdate()->with('rows')->findOrFail($import->id);
            $activity=Activity::query()->lockForUpdate()->findOrFail($locked->activity_id);
            if($activity->completed_at)throw ValidationException::withMessages(['activity'=>__('domain.activity.completed_locked')]);
            if($activity->earnings()->exists())throw ValidationException::withMessages(['activity'=>__('domain.activity.calculated_loot_locked')]);
            if($locked->status!=='draft') throw ValidationException::withMessages(['import'=>__('domain.loot.import_processed')]);
            if($locked->rows->contains('status','invalid')) throw ValidationException::withMessages(['rows'=>__('domain.loot.fix_invalid_rows')]);
            foreach($locked->rows as $row){
                ActivityLoot::query()->create(['activity_id'=>$locked->activity_id,'item_name'=>$row->item_name,'quantity'=>$row->quantity,'unit_price'=>$row->unit_price,'created_by'=>$userId]);
                $item=TreasuryItem::query()->where('item_name',$row->item_name)->lockForUpdate()->first();
                if(!$item)$item=TreasuryItem::query()->create(['item_name'=>$row->item_name,'quantity'=>0,'reserved_quantity'=>0,'unit_value'=>$row->unit_price]);
                $item->update(['quantity'=>$item->quantity+$row->quantity,'unit_value'=>$row->unit_price]);
                TreasuryItemTransaction::query()->create(['treasury_item_id'=>$item->id,'type'=>'loot_income','quantity_delta'=>$row->quantity,'source_activity_id'=>$locked->activity_id,'reason'=>'Подтверждённый импорт #'.$locked->id,'created_by'=>$userId]);
            }
            $locked->update(['status'=>'confirmed','confirmed_at'=>now()]); $this->audit->record('loot_import.confirmed',$locked,['status'=>'draft'],['status'=>'confirmed']);
            return $locked->refresh()->load('rows');
        });
    }
}
