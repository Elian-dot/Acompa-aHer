<?php

namespace App\Http\Requests;

use App\Data\ReportSubmission;
use App\Models\Report;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

class StoreReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Report::class) ?? false;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'report_category_id' => ['required', 'integer', Rule::exists('report_categories', 'id')->where('is_active', true)],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'occurred_at' => ['required', 'date_format:Y-m-d\TH:i:sP', 'before_or_equal:now'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_anonymous' => ['required', 'boolean'],
            'consent' => ['required', 'accepted'],
            'attachments' => ['sometimes', 'array', 'max:2'],
            'attachments.*' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:4096', 'dimensions:max_width=3000,max_height=3000'],
        ];
    }

    public function submission(): ReportSubmission
    {
        $files = $this->file('attachments', []);
        $attachments = is_array($files) ? array_values($files) : [];

        return new ReportSubmission(
            categoryId: $this->integer('report_category_id'),
            latitude: $this->float('latitude'),
            longitude: $this->float('longitude'),
            occurredAt: CarbonImmutable::parse($this->string('occurred_at')->toString())->utc(),
            description: $this->filled('description') ? $this->string('description')->toString() : null,
            isAnonymous: $this->boolean('is_anonymous'),
            attachments: $attachments,
        );
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'report_category_id.exists' => 'Selecciona una categoría activa.',
            'occurred_at.date_format' => 'Indica la fecha del incidente con su zona horaria.',
            'occurred_at.before_or_equal' => 'La fecha del incidente no puede estar en el futuro.',
            'consent.accepted' => 'Confirma que entiendes qué datos se enviarán.',
        ];
    }
}
