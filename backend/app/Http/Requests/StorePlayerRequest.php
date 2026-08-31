<?php

namespace App\Http\Requests;

use App\Enums\PlayerClass;
use App\Enums\UserRole;
use App\Rules\ValidPlayerNickname;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StorePlayerRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->can('create', \App\Models\Player::class) ?? false; }
    protected function prepareForValidation(): void
    {
        $user = $this->user();
        if ($user && !$user->canManageGuild() && $user->hasRole(UserRole::PartyLeader)) {
            $this->merge(['group_id' => $user->player?->group_id]);
        }
    }
    public function rules(): array
    {
        return [
            'nickname' => ['required', 'string', new ValidPlayerNickname(), 'unique:players,nickname'],
            'class' => ['required', Rule::enum(PlayerClass::class)],
            'group_id' => ['nullable', 'integer', 'exists:groups,id'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
