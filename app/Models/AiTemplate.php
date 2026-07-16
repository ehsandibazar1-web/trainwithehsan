<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiTemplate extends Model
{
    protected $fillable = ['name', 'description', 'format', 'content'];
}
