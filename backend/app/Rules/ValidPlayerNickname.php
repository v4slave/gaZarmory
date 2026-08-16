<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class ValidPlayerNickname implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value) || preg_match('/^[\p{Latin}\p{Cyrillic}]{1,18}$/u', $value) !== 1) {
            $fail('Никнейм должен содержать только русские или латинские буквы, без пробелов, цифр и специальных символов, и быть не длиннее 18 символов.');
        }
    }
}
