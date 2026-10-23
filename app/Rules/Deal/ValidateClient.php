<?php

namespace App\Rules\Deal;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\Request;

class ValidateClient implements ValidationRule
{
    protected Request $request;

    public function __construct($request)
    {
        $this->request = $request;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_int($value)) {
            $responsibleId = $this->request->input('responsible_id');
            if ($responsibleId) {
                $responsible = User::find($responsibleId);
                if (! $responsible || ! $responsible->clients()->where('users.id', $value)->exists()) {
                    //                    $fail('The selected client is not a client of the responsible user.');
                }
            } else {
                if (! User::where('id', $value)->exists()) {
                    $fail('The selected client does not exist.');
                }
            }
        } else {
            if (is_string($value)) {
                if (strlen($value) > 255) {
                    $fail('The client string cannot exceed 255 characters.');
                }
            } else {
                $fail('The client must be either an integer (user ID) or a string.');
            }
        }
    }
}
