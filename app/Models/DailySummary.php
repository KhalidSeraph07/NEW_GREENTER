<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class DailySummary extends Model
{
    protected $table = 'h4_summaries';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
    protected $casts = ['snapshot' => 'array', 'artifacts' => 'array', 'result' => 'array'];
}
