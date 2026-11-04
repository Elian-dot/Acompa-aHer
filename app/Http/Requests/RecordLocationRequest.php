<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

class RecordLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $maxAge = config()->integer('safety.location_max_age_seconds');

        return [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy_meters' => ['required', 'numeric', 'min:0', 'max:100000'],
            'recorded_at' => ['required', 'date', 'before_or_equal:'.now()->addMinutes(2)->toIso8601String(), 'after_or_equal:'.now()->subSeconds($maxAge)->toIso8601String()],
        ];
    }

    /** @return array{latitude: float, longitude: float, accuracy_meters: float, recorded_at: CarbonImmutable} */
    public function sample(): array
    {
        return [
            'latitude' => (float) $this->input('latitude'),
            'longitude' => (float) $this->input('longitude'),
            'accuracy_meters' => (float) $this->input('accuracy_meters'),
            'recorded_at' => CarbonImmutable::parse($this->string('recorded_at')->toString()),
        ];
    }
}
