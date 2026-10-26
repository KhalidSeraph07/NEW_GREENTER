<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class BoletaNote extends Model
{
    protected $table = 'h5b_notes';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
    protected $casts = ['snapshot' => 'array', 'artifacts' => 'array', 'result' => 'array', 'number' => 'integer'];
    public function freshTimestamp() { return now('UTC'); }
}
