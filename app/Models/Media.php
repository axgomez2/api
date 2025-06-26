<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Media extends Model
{
    protected $fillable = [
        'mediable_type',
        'mediable_id',
        'file_path',
        'file_name',
        'file_type',
        'file_size',
        'alt_text'
    ];

    // Relacionamento polimórfico com qualquer modelo que possa ter mídia
    public function mediable(): MorphTo
    {
        return $this->morphTo();
    }
}
