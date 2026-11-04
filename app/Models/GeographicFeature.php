<?php

namespace App\Models;

use Database\Factories\GeographicFeatureFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['osm_type', 'osm_id', 'kind', 'name', 'classification', 'lit', 'foot', 'access', 'geometry', 'source_retrieved_at'])]
class GeographicFeature extends Model
{
    /** @use HasFactory<GeographicFeatureFactory> */
    use HasFactory;

    /** @var string */
    protected $dateFormat = 'Y-m-d H:i:s.uP';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['source_retrieved_at' => 'immutable_datetime'];
    }
}
