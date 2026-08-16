<?php

namespace App\Services;

use InvalidArgumentException;
use LogicException;

final class PrimePayoutCalculator
{
    /** @return array{player_share:int,distributed:int,remainder:int} */
    public function calculate(int $goldValue, int $participantsCount): array
    {
        if ($goldValue < 0) {
            throw new InvalidArgumentException('Prime gold value cannot be negative.');
        }
        if ($participantsCount <= 0) {
            throw new InvalidArgumentException('A prime must have at least one participant.');
        }

        $playerShare = intdiv($goldValue, $participantsCount);
        $distributed = $playerShare * $participantsCount;
        $remainder = $goldValue - $distributed;

        if ($distributed > $goldValue || $remainder < 0) {
            throw new LogicException('Prime distribution invariant violated.');
        }

        return [
            'player_share' => $playerShare,
            'distributed' => $distributed,
            'remainder' => $remainder,
        ];
    }
}

