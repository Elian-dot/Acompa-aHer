<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FinishJourneyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return ['outcome' => ['required', 'in:completed,cancelled']];
    }
}
