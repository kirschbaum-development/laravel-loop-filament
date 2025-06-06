<?php

namespace Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TestComment extends Model
{
    protected $fillable = [
        'content',
        'post_id',
        'user_id',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(TestPost::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(TestUser::class);
    }
}
