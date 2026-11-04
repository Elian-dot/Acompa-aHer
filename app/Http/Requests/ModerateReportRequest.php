<?php

namespace App\Http\Requests;

use App\Models\Report;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ModerateReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('moderate', Report::class) ?? false;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['verified', 'discarded'])],
            'privacy_reviewed' => ['required_if:status,verified', 'accepted_if:status,verified'],
            'coverage_reviewed' => ['required_if:status,verified', 'accepted_if:status,verified'],
            'moderation_reason' => ['exclude_unless:status,discarded', 'required', Rule::in(array_keys(config('safety.discard_reasons')))],
        ];
    }
}
