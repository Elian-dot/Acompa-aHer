<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return ['pause_minutes' => ['nullable', 'integer', 'between:1,'.config()->integer('safety.pause_max_minutes')]];
    }
}
