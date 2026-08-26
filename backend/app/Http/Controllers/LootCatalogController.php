<?php
namespace App\Http\Controllers;
use App\Models\LootCatalogItem;
use App\Models\ActivityLoot;
use App\Models\TreasuryItem;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
final class LootCatalogController extends Controller
{
    public function index(){return LootCatalogItem::query()->where('is_active',true)->orderBy('name')->get();}
    public function store(Request $request,AuditService $audit)
    {
        abort_unless($request->user()->canAdministrate(),403);
        $data=$request->validate(['name'=>['required','string','max:255'],'rarity'=>['required',Rule::in(['common','uncommon','rare','unique','epic','legendary','relic','wonder','tale','age_legend','myth','twelve'])],'icon'=>['required','image','mimes:png,jpg,jpeg,webp,gif','max:2048']]);
        $existing=LootCatalogItem::query()->whereRaw('LOWER(name) = LOWER(?)',[$data['name']])->first();
        if($existing?->is_active)throw \Illuminate\Validation\ValidationException::withMessages(['name'=>'Активный предмет с таким названием уже существует.']);
        $iconPath=$data['icon']->store('loot-catalog','public');
        if($existing){$old=$existing->getAttributes();$existing->update(['name'=>$data['name'],'icon_path'=>$iconPath,'rarity'=>$data['rarity'],'is_active'=>true]);$audit->record('loot_catalog.restored',$existing,$old,$existing->getAttributes());return response()->json($existing->refresh(),200);}
        $item=LootCatalogItem::query()->create(['name'=>$data['name'],'icon_path'=>$iconPath,'rarity'=>$data['rarity'],'is_active'=>true]);$audit->record('loot_catalog.created',$item,null,$item->getAttributes());return response()->json($item,201);
    }
    public function update(Request $request,LootCatalogItem $lootCatalogItem,AuditService $audit){abort_unless($request->user()->canAdministrate(),403);$data=$request->validate(['name'=>['sometimes','required','string','max:255',Rule::unique('loot_catalog_items','name')->ignore($lootCatalogItem)],'rarity'=>['sometimes',Rule::in(['common','uncommon','rare','unique','epic','legendary','relic','wonder','tale','age_legend','myth','twelve'])],'icon'=>['nullable','image','mimes:png,jpg,jpeg,webp,gif','max:2048']]);$old=$lootCatalogItem->getAttributes();if(isset($data['name']))$lootCatalogItem->name=$data['name'];if(isset($data['rarity'])){$lootCatalogItem->rarity=$data['rarity'];ActivityLoot::query()->where('loot_catalog_item_id',$lootCatalogItem->id)->update(['rarity'=>$data['rarity']]);TreasuryItem::query()->where('item_name',$lootCatalogItem->name)->update(['rarity'=>$data['rarity']]);}if($request->file('icon'))$lootCatalogItem->icon_path=$request->file('icon')->store('loot-catalog','public');$lootCatalogItem->save();$audit->record('loot_catalog.updated',$lootCatalogItem,$old,$lootCatalogItem->getAttributes());return $lootCatalogItem->refresh();}
    public function destroy(Request $request,LootCatalogItem $lootCatalogItem,AuditService $audit){abort_unless($request->user()->canAdministrate(),403);$data=$request->validate(['updated_at'=>['nullable','date']]);if(isset($data['updated_at'])&&!$lootCatalogItem->updated_at->equalTo($data['updated_at']))throw \Illuminate\Validation\ValidationException::withMessages(['updated_at'=>'Предмет уже изменён другим пользователем. Обновите страницу.']);$lootCatalogItem->update(['is_active'=>false]);$audit->record('loot_catalog.deactivated',$lootCatalogItem,['is_active'=>true],['is_active'=>false]);return response()->noContent();}
}
