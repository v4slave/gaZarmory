<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class TreasuryTokenSetting extends Model
{
    public const CREATED_AT = null;

    protected $table = 'treasury_token_settings';
    protected $fillable = ['token_unit_value'];
    protected function casts(): array { return ['token_unit_value' => 'integer', 'updated_at' => 'immutable_datetime']; }
}
