<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CsvImportLog extends Model
{
    //
// CsvImportLog.php
protected $fillable = [
    'filename',
    'path',
    'valid_rows',
    'invalid_rows',
    'errors',
    'user_id',
];

    protected $casts = [
        'errors' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
