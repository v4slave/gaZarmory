<?php
namespace App\Services;
use App\Models\Activity;
use App\Models\LootImport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;

final class LootTableImporter
{
    private const MAX_ROWS = 5000;
    private const MAX_COLUMNS = 50;

    public function createDraft(Activity $activity, UploadedFile $file, int $userId): LootImport
    {
        $hash=hash_file('sha256',$file->getRealPath());
        if(LootImport::query()->where('activity_id',$activity->id)->where('file_hash',$hash)->exists()) throw ValidationException::withMessages(['file'=>'Этот файл уже импортирован для события.']);
        try {
            $type = IOFactory::identify($file->getRealPath());
            $reader = IOFactory::createReader($type);
            $reader->setReadDataOnly(true);
            if (method_exists($reader, 'setReadEmptyCells')) {
                $reader->setReadEmptyCells(false);
            }

            $worksheetInfo = $reader->listWorksheetInfo($file->getRealPath())[0] ?? null;
            if (($worksheetInfo['totalRows'] ?? 0) > self::MAX_ROWS + 1) {
                throw ValidationException::withMessages(['file' => 'В таблице допускается не более '.self::MAX_ROWS.' строк лута.']);
            }
            if (($worksheetInfo['totalColumns'] ?? 0) > self::MAX_COLUMNS) {
                throw ValidationException::withMessages(['file' => 'В таблице слишком много столбцов. Оставьте item_name, quantity и unit_price.']);
            }

            $spreadsheet = $reader->load($file->getRealPath());
            $sheet = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable) {
            throw ValidationException::withMessages(['file' => 'Не удалось прочитать таблицу. Сохраните её как CSV или XLSX и попробуйте снова.']);
        }
        if(count($sheet)<2) throw ValidationException::withMessages(['file'=>'Таблица не содержит строк лута.']);
        $headers=array_map(fn($v)=>mb_strtolower(trim((string)$v)),array_shift($sheet));
        $map=array_flip($headers);
        foreach(['item_name','quantity','unit_price'] as $required) if(!array_key_exists($required,$map)) throw ValidationException::withMessages(['file'=>"Отсутствует колонка {$required}."]);
        return DB::transaction(function()use($activity,$file,$userId,$hash,$sheet,$map){
            $import=LootImport::query()->create(['activity_id'=>$activity->id,'created_by'=>$userId,'source_type'=>'table','original_filename'=>$file->getClientOriginalName(),'file_hash'=>$hash,'status'=>'draft']);
            foreach($sheet as $offset=>$data){
                $name=trim((string)($data[$map['item_name']]??'')); if($name==='')continue;
                $quantity=filter_var($data[$map['quantity']]??null,FILTER_VALIDATE_INT); $price=filter_var($data[$map['unit_price']]??null,FILTER_VALIDATE_INT);
                $valid=$quantity!==false&&$quantity>0&&$price!==false&&$price>=0;
                $import->rows()->create(['row_number'=>$offset+2,'item_name'=>$name,'quantity'=>$valid?$quantity:0,'unit_price'=>$valid?$price:0,'status'=>$valid?'valid':'invalid','raw_data'=>$data]);
            }
            if(!$import->rows()->exists()) throw ValidationException::withMessages(['file'=>'Не найдено ни одного предмета.']);
            return $import->load('rows');
        });
    }
}
