<?php
namespace App\Http\Controllers;
use App\Actions\SellTreasuryItem;use App\Models\TreasuryItem;use Illuminate\Http\Request;
final class TreasuryItemSaleController extends Controller { public function __invoke(Request $request,TreasuryItem $item,SellTreasuryItem $action):TreasuryItem{abort_unless($request->user()->canHandleTreasuryItems(),403);$data=$request->validate(['quantity'=>['required','integer','min:1'],'total_amount'=>['required','integer','min:1'],'comment'=>['required','string','max:500']]);return $action->execute($item,$data['quantity'],$data['total_amount'],$data['comment'],$request->user()->id);} }
