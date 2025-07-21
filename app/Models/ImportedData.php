<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportedData extends Model
{
    protected $casts = [
        'data' => 'array',
    ];
    protected $fillable = [
        'content',
        'data',
        'taxonomy_terms',
        'title',
        'route_url',
        'status',
        'sites',
        'locale',
        'published_at',
    ];
}
