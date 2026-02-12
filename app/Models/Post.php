<?php

namespace App\Models;

use App\Models\FacebookImages;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Post extends Model
{
    protected $fillable = [
        'title',
        'link',
    ];

    public function images(): HasMany
    {
        return $this->hasMany(FacebookImages::class)->orderBy('order');
    }
}
