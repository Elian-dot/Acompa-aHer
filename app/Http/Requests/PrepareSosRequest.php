<?php

namespace App\Http\Requests;

use App\Models\EmergencyContact;
use Illuminate\Foundation\Http\FormRequest;

class PrepareSosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', EmergencyContact::class) ?? false;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'confirmed' => ['required', 'accepted'],
            'contact_ids' => ['required', 'array', 'min:1', 'max:10'],
            'contact_ids.*' => ['required', 'integer', 'distinct'],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
        ];
    }
}
