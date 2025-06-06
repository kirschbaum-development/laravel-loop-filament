<?php

namespace Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TestPost extends Model
{
    protected $fillable = [
        'title',
        'content',
        'user_id',
        'category_id',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(TestUser::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TestComment::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TestCategory::class);
    }
}
