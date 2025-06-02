<?php

namespace Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TestUser extends Model
{
    protected $fillable = [
        'name',
        'email',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function posts(): HasMany
    {
        return $this->hasMany(TestPost::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TestComment::class);
    }
}