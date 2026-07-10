<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Media extends Model
{
    protected $fillable = ['original_name', 'disk_path', 'url', 'type', 'mime_type', 'size'];
}
