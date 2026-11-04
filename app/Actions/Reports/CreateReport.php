<?php

namespace App\Actions\Reports;

use App\Data\GeographicCoverage;
use App\Data\ReportSubmission;
use App\Models\Report;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class CreateReport
{
    public function __construct(private GeographicCoverage $coverage) {}

    public function handle(User $user, ReportSubmission $data): Report
    {
        $this->coverage->validatePoint($data->longitude, $data->latitude, 'location');
        $paths = [];

        try {
            return DB::transaction(function () use ($user, $data, &$paths): Report {
                $report = new Report([
                    'report_category_id' => $data->categoryId,
                    'is_anonymous' => $data->isAnonymous,
                    'location' => sprintf('SRID=4326;POINT(%.8F %.8F)', $data->longitude, $data->latitude),
                    'occurred_at' => $data->occurredAt,
                    'description' => $data->description,
                ]);
                if (! $report->is_anonymous) {
                    $report->user()->associate($user);
                }
                $report->save();

                foreach ($data->attachments as $upload) {
                    $contents = $this->sanitizeImage($upload);
                    $path = 'report-attachments/'.Str::uuid().'.jpg';
                    $paths[] = $path;
                    if (! Storage::disk('local')->put($path, $contents)) {
                        throw new RuntimeException('No se pudo guardar el adjunto privado.');
                    }
                    $report->attachments()->create([
                        'disk' => 'local', 'path' => $path, 'mime_type' => 'image/jpeg', 'size_bytes' => strlen($contents),
                    ]);
                }

                return $report;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($paths);
            throw $exception;
        }
    }

    private function sanitizeImage(UploadedFile $upload): string
    {
        $image = @imagecreatefromstring($upload->getContent());
        if ($image === false) {
            throw ValidationException::withMessages(['attachments' => 'No se pudo procesar la imagen.']);
        }

        ob_start();
        try {
            if (! imagejpeg($image, null, 85)) {
                throw new RuntimeException('No se pudo preparar la imagen.');
            }
            $contents = ob_get_contents();
            if ($contents === false) {
                throw new RuntimeException('No se pudo preparar la imagen.');
            }

            return $contents;
        } finally {
            ob_end_clean();
        }
    }
}
