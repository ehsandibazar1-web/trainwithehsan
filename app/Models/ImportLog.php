<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportLog extends Model
{
    protected $fillable = [
        'user_id', 'source', 'ai_provider', 'format', 'status',
        'errors', 'warnings', 'article_id', 'article_title', 'locale',
        'faq_count', 'image_count',
    ];

    protected $casts = [
        'errors' => 'array',
        'warnings' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function article()
    {
        return $this->belongsTo(Article::class);
    }
}
