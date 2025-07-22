<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MapData extends Model
{
    protected $table = 'map_data';

    protected $fillable  = [
        'original_data',
        'mapped_data',
    ];
}
