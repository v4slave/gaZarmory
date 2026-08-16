<?php
namespace App\Http\Controllers;
use App\Actions\CalculatePrimeShares;
use App\Models\Activity;
use Illuminate\Http\Request;
final class PrimeCalculationController extends Controller
{
    public function __invoke(Request $request,Activity $activity,CalculatePrimeShares $action):Activity{$this->authorize('update',$activity);return $action->execute($activity);}
}
