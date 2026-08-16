<?php
namespace App\Console\Commands;
use App\Actions\FinishAuction;
use App\Models\Auction;
use Illuminate\Console\Command;
use Throwable;
final class FinishExpiredAuctions extends Command
{
    protected $signature='auctions:finish-expired';
    protected $description='Завершает активные аукционы с истёкшим временем';
    public function handle(FinishAuction $action):int
    {
        $failed=0;
        Auction::query()->where('status','active')->where('ends_at','<=',now())->orderBy('id')->each(function(Auction $auction)use($action,&$failed){try{$action->execute($auction,$auction->created_by);$this->info('Аукцион #'.$auction->id.' завершён.');}catch(Throwable $e){$failed++;$this->error('Аукцион #'.$auction->id.': '.$e->getMessage());}});
        return $failed===0?self::SUCCESS:self::FAILURE;
    }
}
