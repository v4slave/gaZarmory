<?php

namespace App\Http\Requests;

use App\Enums\PlayerClass;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StorePlayerRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->can('create', \App\Models\Player::class) ?? false; }
    public function rules(): array
    {
        return [
            'nickname' => ['required', 'string', 'max:120', 'unique:players,nickname'],
            'class' => ['required', Rule::enum(PlayerClass::class)],
            'group_id' => ['nullable', 'integer', 'exists:groups,id'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}

