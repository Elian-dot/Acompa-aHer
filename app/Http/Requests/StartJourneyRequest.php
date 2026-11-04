<?php

namespace App\Http\Requests;

use App\Models\Journey;
use Illuminate\Foundation\Http\FormRequest;

class StartJourneyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('start', Journey::class) ?? false;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'origin.latitude' => ['required', 'numeric', 'between:-90,90'],
            'origin.longitude' => ['required', 'numeric', 'between:-180,180'],
            'destination.latitude' => ['required', 'numeric', 'between:-90,90'],
            'destination.longitude' => ['required', 'numeric', 'between:-180,180'],
            'route' => ['nullable', 'array', 'min:2', 'max:5000'],
            'route.*' => ['required', 'array', 'size:2'],
            'route.*.0' => ['required', 'numeric', 'between:-180,180'],
            'route.*.1' => ['required', 'numeric', 'between:-90,90'],
            'contact_ids' => ['required', 'array', 'min:1', 'max:10'],
            'contact_ids.*' => ['required', 'integer', 'distinct'],
            'inactivity_minutes' => ['nullable', 'integer', 'between:'.(config()->integer('safety.min_inactivity_seconds') / 60).','.(config()->integer('safety.max_inactivity_seconds') / 60)],
            'share_hours' => ['nullable', 'integer', 'between:1,'.config()->integer('safety.share_max_hours')],
            'consent' => ['required', 'accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'consent.accepted' => 'Debes autorizar compartir tu ubicación con las personas elegidas.',
            'contact_ids.required' => 'Elige al menos una persona de confianza.',
        ];
    }
}
