<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MediaFolder extends Model
{
    protected $fillable = ['name', 'parent_id'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(MediaFolder::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(MediaFolder::class, 'parent_id')->orderBy('name');
    }

    public function media(): HasMany
    {
        return $this->hasMany(Media::class, 'folder_id');
    }

    // مسیر کامل برای نمایش در breadcrumb و منوی انتخاب پوشه
    public function fullPath(): string
    {
        return $this->parent ? $this->parent->fullPath().' / '.$this->name : $this->name;
    }

    public function isEmpty(): bool
    {
        return ! $this->children()->exists() && ! $this->media()->exists();
    }
}
