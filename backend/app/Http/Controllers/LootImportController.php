<?php
namespace App\Http\Controllers;
use App\Actions\ConfirmLootImport;
use App\Models\Activity;
use App\Models\LootImport;
use App\Models\LootImportRow;
use App\Services\LootTableImporter;
use Illuminate\Http\Request;

final class LootImportController extends Controller
{
    public function store(Request $request,Activity $activity,LootTableImporter $importer){$this->authorize('update',$activity);abort_if($activity->completed_at,409,__('domain.activity.completed_locked'));abort_if($activity->earnings()->exists(),409,__('domain.activity.calculated_loot_locked'));$data=$request->validate(['file'=>['required','file','mimes:csv,txt,xlsx,xls','max:10240']]);return response()->json($importer->createDraft($activity,$data['file'],$request->user()->id),201);}
    public function show(Request $request,LootImport $lootImport){abort_unless($request->user()->canManageGuild(),403);return $lootImport->load('rows');}
    public function updateRow(Request $request,LootImport $lootImport,LootImportRow $row)
    {
        abort_unless($request->user()->canManageGuild(),403);
        abort_unless($row->loot_import_id===$lootImport->id,404);
        abort_if($lootImport->status!=='draft',409,__('domain.loot.confirmed_import_locked'));
        $data=$request->validate(['item_name'=>['required','string','max:255'],'quantity'=>['required','integer','min:1'],'unit_price'=>['required','integer','min:0']]);
        $row->update($data+['status'=>'valid']);
        return $row->refresh();
    }
    public function confirm(Request $request,LootImport $lootImport,ConfirmLootImport $action){abort_unless($request->user()->canManageGuild(),403);return $action->execute($lootImport,$request->user()->id);}
}
