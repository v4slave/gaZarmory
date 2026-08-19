<?php
namespace App\Http\Controllers;
use App\Models\Payout;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
final class PayoutExportController extends Controller
{
 public function __invoke(Request $request,Payout $payout){abort_unless($request->user()->canManageGuild()||($request->user()->player&&$payout->players()->where('player_id',$request->user()->player->id)->exists()),403);$data=$request->validate(['format'=>['required',Rule::in(['csv','xlsx'])],'search'=>['nullable','string','max:120'],'status'=>['nullable',Rule::in(['pending','paid','cancelled'])]]);$rows=$payout->players()->when($data['search']??null,fn($q,$v)=>$q->where('nickname_snapshot','ilike','%'.str_replace(['%','_'],['\%','\_'],$v).'%'))->when($data['status']??null,fn($q,$v)=>$q->where('status',$v))->orderBy('nickname_snapshot')->get();$name='payout-'.$payout->id;if($data['format']==='csv')return response()->streamDownload(function()use($rows){$out=fopen('php://output','wb');fwrite($out,"\xEF\xBB\xBF");fputcsv($out,['Игрок','Посещаемость, %','Праймы','Сумма','Статус','Выдано'], ';');foreach($rows as $row)fputcsv($out,[$row->nickname_snapshot,$row->prime_attendance_percentage_snapshot,$row->primes_count,$row->amount,$row->status,$row->paid_at],';');fclose($out);},$name.'.csv',['Content-Type'=>'text/csv; charset=UTF-8']);$book=new Spreadsheet();$sheet=$book->getActiveSheet();$sheet->fromArray(['Игрок','Посещаемость, %','Праймы','Сумма','Статус','Выдано'],null,'A1');$index=2;foreach($rows as $row)$sheet->fromArray([$row->nickname_snapshot,(float)$row->prime_attendance_percentage_snapshot,$row->primes_count,$row->amount,$row->status,$row->paid_at?->format('d.m.Y H:i')],null,'A'.$index++);$sheet->getStyle('A1:F1')->getFont()->setBold(true);foreach(range('A','F') as $col)$sheet->getColumnDimension($col)->setAutoSize(true);$path=tempnam(sys_get_temp_dir(),'payout-');(new Xlsx($book))->save($path);$book->disconnectWorksheets();return response()->download($path,$name.'.xlsx')->deleteFileAfterSend(true);}
}
