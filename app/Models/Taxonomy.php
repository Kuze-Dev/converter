<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Taxonomy extends Model
{
    protected $table = 'taxonomies';

    protected $fillable = [
        'original_data',
        'converted_value'
    ];
}
