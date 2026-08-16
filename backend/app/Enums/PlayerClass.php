<?php

namespace App\Enums;

enum PlayerClass: string
{
    case Melee = 'melee';
    case Archer = 'archer';
    case Mage = 'mage';
    case Healer = 'healer';
    case Bard = 'bard';
    case Tank = 'tank';
}

