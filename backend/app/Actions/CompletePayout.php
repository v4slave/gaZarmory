<?php
namespace App\Actions;
use App\Models\Payout;
final class CompletePayout
{
    public function __construct(private readonly PayPayoutPlayers $payPlayers){}
    public function execute(Payout $payout,int $userId):Payout
    {
        return $this->payPlayers->execute($payout,$payout->players()->where('status','pending')->pluck('player_id')->all(),$userId);
    }
}
