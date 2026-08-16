<?php

namespace App\Http\Requests;

use App\Enums\PlayerClass;
use App\Models\Player;
use App\Rules\ValidPlayerNickname;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdatePlayerRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->can('update', $this->route('player')) ?? false; }
    public function rules(): array
    {
        /** @var Player $player */
        $player = $this->route('player');
        return [
            'nickname' => ['sometimes', 'required', 'string', new ValidPlayerNickname(), Rule::unique('players', 'nickname')->ignore($player)],
            'class' => ['sometimes', 'required', Rule::enum(PlayerClass::class)],
            'group_id' => ['sometimes', 'nullable', 'integer', 'exists:groups,id'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
