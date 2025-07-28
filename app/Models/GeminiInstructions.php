<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GeminiInstructions extends Model
{
    protected $table = 'gemini_instructions';

    protected $fillable = [
        'title',
        'content',
    ];
}
