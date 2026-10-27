<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class NoteSummary extends Model
{
    protected $table = 'h5b_summaries';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
    protected $casts = ['snapshot' => 'array', 'artifacts' => 'array', 'result' => 'array'];
    public function freshTimestamp() { return now('UTC'); }
}
