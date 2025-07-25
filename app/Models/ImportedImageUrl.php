<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportedImageUrl extends Model
{
    //
    protected $table = 'imported_image_urls';
    protected $fillable = ['image_url'];
}
