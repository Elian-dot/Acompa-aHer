<?php

namespace App\Models;

use Database\Factories\ZoneFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code', 'name', 'boundary'])]
class Zone extends Model
{
    /** @use HasFactory<ZoneFactory> */
    use HasFactory;

    /** @var string */
    protected $dateFormat = 'Y-m-d H:i:s.uP';
}
